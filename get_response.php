<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db_config.php';

if (!isset($_GET['id'])) {
    die("No ID provided");
}

$response_id = (int) $_GET['id'];

// Fetch response details
$stmt = $conn->prepare("SELECT * FROM responses WHERE id = ?");
$stmt->bind_param("i", $response_id);
$stmt->execute();
$response = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$response) {
    die("Response not found");
}

// Fetch form details
$stmt = $conn->prepare("SELECT form_title, form_structure FROM review_forms WHERE id = ?");
$stmt->bind_param("i", $response['review_form_id']);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Decode JSON data with error checking
$response_data = json_decode($response['response_data'], true);
$form_structure = json_decode($form['form_structure'], true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error decoding JSON data: " . json_last_error_msg());
}
?>
<div class="p-4">
    <div class="response-header mb-4">
        <h4 class="mb-2"><?= htmlspecialchars($form['form_title']) ?></h4>
        <p class="text-muted mb-3">
            <i class="far fa-calendar-alt me-2"></i>
            Submitted on <?= date('M j, Y h:i A', strtotime($response['created_at'])) ?>
        </p>

        <div class="d-flex flex-wrap gap-4 mb-4">
            <div class="metric-card px-3 py-2">
                <div class="metric-label">Overall Rating</div>
                <div class="metric-value text-warning">
                    <?= number_format($response['rating_average'], 1) ?>/5.0
                </div>
                <div class="star-rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i
                            class="fas fa-star <?= $i <= round($response['rating_average']) ? 'text-warning' : 'text-muted' ?>"></i>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="metric-card px-3 py-2">
                <div class="metric-label">Sentiment</div>
                <div class="metric-value">
                    <span
                        class="badge bg-<?= $response['sentiment'] === 'positive' ? 'success' : ($response['sentiment'] === 'negative' ? 'danger' : 'warning') ?> p-2">
                        <i
                            class="fas fa-<?= $response['sentiment'] === 'positive' ? 'smile' : ($response['sentiment'] === 'negative' ? 'frown' : 'meh') ?> me-1"></i>
                        <?= ucfirst($response['sentiment']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="response-content">
        <?php if (!empty($form_structure['sections'])): ?>
            <?php foreach ($form_structure['sections'] as $section): ?>
                <div class="section-card mb-4">
                    <div class="section-header mb-3">
                        <h5 class="mb-0"><?= htmlspecialchars($section['title']) ?></h5>
                    </div>

                    <?php if (!empty($section['fields'])): ?>
                        <?php foreach ($section['fields'] as $field): ?>
                            <?php if (isset($response_data[$field['id']])): ?>
                                <div class="field-item mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong><?= htmlspecialchars($field['label']) ?></strong>
                                        <?php if ($field['type'] === 'rating'): ?>
                                            <div class="star-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i
                                                        class="fas fa-star <?= $i <= $response_data[$field['id']] ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($field['type'] === 'text' || $field['type'] === 'textarea'): ?>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" placeholder="<?= htmlspecialchars($field['label']) ?>"
                                                id="field_<?= $field['id'] ?>"
                                                style="height: <?= $field['type'] === 'textarea' ? '100px' : 'auto' ?>"
                                                readonly><?= htmlspecialchars(is_array($response_data[$field['id']]) ? implode(', ', $response_data[$field['id']]) : $response_data[$field['id']]) ?></textarea>
                                            <label for="field_<?= $field['id'] ?>"><?= htmlspecialchars($field['label']) ?></label>
                                        </div>
                                    <?php elseif ($field['type'] === 'select' || $field['type'] === 'radio' || $field['type'] === 'checkbox'): ?>
                                        <div class="response-value p-2 bg-light rounded">
                                            <?php if (is_array($response_data[$field['id']])): ?>
                                                <?= implode(', ', array_map('htmlspecialchars', $response_data[$field['id']])) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($response_data[$field['id']]) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($field['type'] !== 'rating'): ?>
                                        <div class="response-value p-2 bg-light rounded">
                                            <?= nl2br(htmlspecialchars($response_data[$field['id']])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>