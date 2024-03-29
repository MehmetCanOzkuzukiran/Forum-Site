<?php
session_start();

include ("../php/connectdb.php"); //db connection

$conn = new mysqli($servername, $username, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Assuming you have the post details available
$postId = $_GET['post_id'];

// Fetch post details using prepared statement
$sqlPost = "SELECT posts.Title, posts.Content, posts.DatePosted, users.Username, posts.totalRating, posts.ratingCount, users.profilePicture, users.ID
            FROM posts 
            JOIN users ON posts.userID = users.ID 
            WHERE posts.postID = ?";
$stmtPost = $conn->prepare($sqlPost);
$stmtPost->bind_param('i', $postId);
$stmtPost->execute();
$resultPost = $stmtPost->get_result();

if ($resultPost->num_rows > 0) {
    $rowPost = $resultPost->fetch_assoc();
    $postTitle = $rowPost['Title'];
    $postContent = $rowPost['Content'];
    $postDate = $rowPost['DatePosted'];
    $postUsername = $rowPost['Username'];
    $totalRating = $rowPost['totalRating'];
    $ratingCount = $rowPost['ratingCount'];
    $profilePicture = $rowPost['profilePicture'];
    $userIddb = $rowPost['ID'];
} else {
    echo "Post not found.";
    exit();
}
$leadingSlash = "/";
$fullProfilePicturePath = $leadingSlash . $profilePicture;

// Check if the user has already rated the post
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    $stmtCheckRating = $conn->prepare("SELECT ratingID, ratingValue FROM ratings WHERE postID = ? AND userID = ?");
    $stmtCheckRating->bind_param('ii', $postId, $userId);
    $stmtCheckRating->execute();
    $resultCheckRating = $stmtCheckRating->get_result();

    if ($resultCheckRating->num_rows > 0) {
        $hasRated = true;
        $previousRatingValue = $resultCheckRating->fetch_assoc()['ratingValue'];
    } else {
        $hasRated = false;
        $previousRatingValue = 0;
    }
}

// Fetch comments for the post using prepared statement
$sqlComments = "SELECT comments.CommentID, comments.Content, comments.DateCommented, users.Username, comments.UserID, users.profilePicture 
                FROM comments 
                JOIN users ON comments.UserID = users.ID 
                WHERE comments.PostID = ?";
$stmtComments = $conn->prepare($sqlComments);
$stmtComments->bind_param('i', $postId);
$stmtComments->execute();
$resultComments = $stmtComments->get_result();

// Initialize $comments as an empty array
$comments = [];

// Collect comments in an array
if ($resultComments->num_rows > 0) {
    while ($rowComment = $resultComments->fetch_assoc()) {
        $comments[] = $rowComment;
    }
}

// Handle rating form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rating'])) {
    $userId = $_SESSION['user_id'];
    $ratingValue = $_POST['rating'];

    if ($hasRated) {
        // Update the existing rating
        $stmtUpdateRating = $conn->prepare("UPDATE ratings SET ratingValue = ? WHERE postID = ? AND userID = ?");
        $stmtUpdateRating->bind_param('iii', $ratingValue, $postId, $userId);

        if ($stmtUpdateRating->execute()) {
            echo "Rating updated successfully!";
            // Update total_rating in the posts table
            $newTotalRating = $totalRating - $previousRatingValue + $ratingValue;

            $stmtUpdatePost = $conn->prepare("UPDATE posts SET totalRating = ? WHERE postID = ?");
            $stmtUpdatePost->bind_param('ii', $newTotalRating, $postId);
            $stmtUpdatePost->execute();

            // Update the value in the current session for immediate display
            $totalRating = $newTotalRating;
        } else {
            echo "Error updating rating: " . $conn->error;
        }
    } else {
        // Insert the rating into the ratings table
        $stmtInsertRating = $conn->prepare("INSERT INTO ratings (postID, userID, ratingValue, dateCreated) VALUES (?, ?, ?, NOW())");
        $stmtInsertRating->bind_param('iii', $postId, $userId, $ratingValue);

        if ($stmtInsertRating->execute()) {
            echo "Rating submitted successfully!";
            // Update total_rating and rating_count in the posts table
            $newTotalRating = $totalRating + $ratingValue;
            $newRatingCount = $ratingCount + 1;

            $stmtUpdatePost = $conn->prepare("UPDATE posts SET totalRating = ?, ratingCount = ? WHERE postID = ?");
            $stmtUpdatePost->bind_param('iii', $newTotalRating, $newRatingCount, $postId);
            $stmtUpdatePost->execute();

            // Update the values in the current session for immediate display
            $totalRating = $newTotalRating;
            $ratingCount = $newRatingCount;
        } else {
            echo "Error submitting rating: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Details</title>
    <link rel="stylesheet" href="../css/post.css">
    <link rel="stylesheet" href="../css/mainNav.css">
    <script src="../js/script.js"></script>
</head>

<body>
    <!-- Your existing navigation code -->
    <nav class="main-nav">
        <h1 class="brand">Welcome to My Forum</h1>
    </nav>
    <div class="post-container">
        <div class="post">
            <h2 class="post-title"><?php echo htmlspecialchars($postTitle); ?></h2>
            <div class="in-post-area">
                <div class="user-area">
                    <img class="post-avatar" id="previewImage" src="<?php echo htmlspecialchars($fullProfilePicturePath) . '?version=' . uniqid(); ?>" alt="UserImage">
                    <p class="post-meta">By <?php echo htmlspecialchars($postUsername); ?> on <?php echo htmlspecialchars($postDate); ?></p>
                    <p class="post-rating">Average Rating: <?php echo number_format($totalRating / ($ratingCount > 0 ? $ratingCount : 1), 1); ?> (<?php echo $ratingCount; ?> ratings)</p>
                </div>
            <?php
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $rowPost['ID']) {
                    echo "<form action='../php/delete_post.php' method='POST'>";
                    echo "<input type='hidden' name='post_id' value='$postId'>";
                    echo "<button type='submit' class='delete-button post-delete'>Delete Post</button>";
                    echo "</form>";
                }
                ?>
                <p class="post-content"><?php echo htmlspecialchars($postContent); ?></p>
            </div>
            <!-- Rating form -->
            <form action="../html/post.php?post_id=<?php echo $postId; ?>" method="POST">
                <label for="rating">Rate this post:</label>
                <select name="rating" id="rating" required>
                    <option value="1">1 - Poor</option>
                    <option value="2">2 - Fair</option>
                    <option value="3">3 - Average</option>
                    <option value="4">4 - Good</option>
                    <option value="5">5 - Excellent</option>
                </select>
                <button type="submit">Submit Rating</button>
            </form>
        </div>

        <div  class="comments">
            <h3 class="comment-section">Comments:</h3>
            <?php
            foreach ($comments as $comment) {
                echo "<div class='comment'>";
                echo "<div class='comment-user'>";
                echo "<img class='comment-avatar' src='/" . (htmlspecialchars($comment['profilePicture']) . "'>");
                echo "<p class='comment-username comment-insections'>" . (isset($comment['Username']) ? 'created by:' . htmlspecialchars($comment['Username']) : 'Anonymous') . "</p>";
                echo "<p class='comment-date comment-insections'>" . (isset($comment['DateCommented']) ? 'commented on: ' . htmlspecialchars($comment['DateCommented']) : '') . "</p>";
                echo "</div>";
                echo "<p class='comment-content comment-insections'>" . (isset($comment['Content']) ? htmlspecialchars($comment['Content']) : '') . "</p>";
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['UserID']) {
                    echo "<form action='../php/delete_comment.php' method='POST'>";
                    echo "<input type='hidden' name='comment_id' value='{$comment['CommentID']}'>";
                    echo "<button type='submit' class='delete-button'>Delete</button>";
                    echo "</form>";
                } else {
                }
                echo "</div>";
                
                // Add delete button if the comment belongs to the logged-in user
                
            }
            if(!isset($comment['UserID']))
                echo "<p>You didn't comments yet.</p>";
            ?>

            <h3 class="add-comment">Add a Comment:</h3>
            <form action="../php/process_comment.php" method="POST">
                <textarea name="commentContent" placeholder="Type your comment here"></textarea>
                <input type="hidden" name="postId" value="<?php echo $postId; ?>">
                <button type="submit">Post Comment</button>
            </form>
            <div class="home">
                <p><a class="home-button" href="../index.php">Return to home</a></p>
            </div>
        </div>
    </div>
</body>

<footer>&copy; 2023 forum. All rights reserved.</footer>
</html>

