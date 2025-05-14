<?php
session_start();

//authentication checker = auth_check

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php[{$stall_id}]');
    exit();
}

// You can add role-based checks here if needed
?>

## 3. Create `review_details.php` to view individual reviews

```php
<?php
require_once 'db_config.php';
require_once 'auth_check.php';

if (!isset($_GET['id'])) {
    header('Location: analytics.php');
    exit();
}

$review_id = $_GET['id'];

// Get review details
$stmt = $conn->prepare("
    SELECT 
        r.*,
        f.stall_name,
        rf.form_title,
        rf.form_structure
    FROM responses r
    JOIN food_stalls f ON r.food_stall_id = f.id
    JOIN review_forms rf ON r.review_form_id = rf.id
    WHERE r.id = ?
");
$stmt->bind_param('i', $review_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();

if (!$review) {
    header('Location: analytics.php');
    exit();
}

// Decode the response data
$responses = json_decode($review['response_data'], true);
$form_structure = json_decode($review['form_structure'], true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Review Details</title>
    <!-- Include your CSS files -->
</head>

<body>
    <div class="container py-4">
        <a href="analytics.php" class="btn btn-outline-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Analytics
        </a>

        <div class="card">
            <div class="card-header">
                <h3>Review Details</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <p><strong>Food Stall:</strong> <?= htmlspecialchars($review['stall_name']) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Form:</strong> <?= htmlspecialchars($review['form_title']) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($review['created_at'])) ?></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Average Rating</h5>
                                <div class="stat-value">
                                    <?= number_format($review['rating_average'], 1) ?>/5
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Sentiment</h5>
                                <div class="stat-value <?= $review['sentiment'] ?>">
                                    <?= ucfirst($review['sentiment']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h4 class="mb-3">Responses</h4>
                <?php foreach ($form_structure as $section_index => $section): ?>
                    <?php if (!empty($section['title'])): ?>
                        <h5><?= htmlspecialchars($section['title']) ?></h5>
                    <?php endif; ?>

                    <div class="mb-4">
                        <?php foreach ($section['fields'] as $field_index => $field): ?>
                            <?php $response = $responses[$section_index][$field_index] ?? null; ?>
                            <?php if ($response): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <h6><?= htmlspecialchars($field['label']) ?></h6>

                                    <?php if (isset($response['ratings'])): ?>
                                        <ul>
                                            <?php foreach ($field['ratings'] as $rating_index => $rating_field): ?>
                                                <li>
                                                    <?= htmlspecialchars($rating_field['label']) ?>:
                                                    <?= $response['ratings'][$rating_index] ?? 'N/A' ?>/5
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php elseif (isset($response['value'])): ?>
                                        <div class="p-2 bg-light rounded">
                                            <?= nl2br(htmlspecialchars($response['value'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>

</html>