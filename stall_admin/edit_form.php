<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db_config.php';

// Check if form hash is provided
if (!isset($_GET["form"])) {
    die("Form parameter is missing");
}

$form_hash = $_GET["form"];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the edited form structure
    $form_structure = json_decode($_POST['form_structure'], true);

    // Validate JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Invalid JSON structure: " . json_last_error_msg());
    }

    // Update the form in database
    $stmt = $conn->prepare("UPDATE review_forms SET form_structure = ? WHERE form_hash = ?");
    $encoded_form_structure = json_encode($form_structure);
    $stmt->bind_param("ss", $encoded_form_structure, $form_hash);

    if ($stmt->execute()) {
        // Redirect back to edit page with success message
        header("Location: edit_form.php?form=" . $form_hash . "&success=1");
        exit();
    } else {
        die("Error updating form: " . $conn->error);
    }
}

// Fetch form details
$stmt = $conn->prepare("
    SELECT rf.*, fs.stall_name
    FROM review_forms rf
    JOIN food_stalls fs ON rf.food_stall_id = fs.id
    WHERE rf.form_hash = ?
");
$stmt->bind_param("s", $form_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Form not found");
}

$form = $result->fetch_assoc();
$stmt->close();

// Decode the form structure
$json_string = $form["form_structure"];
$form_structure = json_decode($json_string, true);

// If first decode returns a string, decode again (for double-encoded JSON)
if (is_string($form_structure)) {
    $form_structure = json_decode($form_structure, true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON structure: " . json_last_error_msg());
}

// Ensure we have an array
if (!is_array($form_structure)) {
    die("Form structure is not an array");
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <title>Edit Form: <?= htmlspecialchars($form["form_title"]) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B;
            --dark-color: #2D3436;
            --gradient-primary: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #2D3436;
            padding: 20px;
        }

        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 0;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
        }

        .form-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .form-section {
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }

        .question-card {
            padding: 1.5rem;
            border: 2px dashed #eee;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            background: #f9f9f9;
            position: relative;
        }

        .question-label {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .required-field::after {
            content: " *";
            color: var(--primary-color);
        }

        .star-rating {
            color: #ddd;
            font-size: 1.4rem;
        }

        .star-rating i {
            margin-right: 0.3rem;
        }

        textarea.form-control {
            border: 2px solid #eee;
            border-radius: 8px;
            padding: 1rem;
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .star-rating {
            color: #ffc107;
            cursor: pointer;
            font-size: 1.4rem;
        }

        .fa-regular.fa-star {
            color: #ddd;
        }

        .fa-solid.fa-star {
            color: #ffc107;
        }

        .rating-question {
            margin-bottom: 10px;
        }

        .rating-question .question-label {
            margin-bottom: 10px;
        }

        .edit-controls {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .edit-controls button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1.2rem;
            margin-left: 5px;
        }

        .edit-controls button:hover {
            opacity: 0.8;
        }

        .add-section-btn,
        .add-question-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            margin: 10px 0;
            cursor: pointer;
        }

        .question-type-selector {
            margin: 15px 0;
            padding: 10px;
            border: 1px dashed #ccc;
            border-radius: 5px;
        }

        .success-message {
            background: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-align: center;
        }

        /* New styles for dish ratings */
        .dish-rating {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .dish-rating-label {
            font-weight: 500;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="edit-container">
        <div class="form-header">
            <h2>Edit: <?= htmlspecialchars($form["form_title"]) ?></h2>
            <p class="mb-0"><?= htmlspecialchars($form["form_description"] ?? "") ?></p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Form updated successfully!
            </div>
        <?php endif; ?>

        <form id="editForm" method="POST" action="edit_form.php?form=<?= $form_hash ?>">
            <input type="hidden" name="form_structure" id="formStructureInput">

            <div class="form-content" id="formContent">
                <?php if (empty($form_structure)): ?>
                    <div class="alert alert-warning m-4">No questions found in this form</div>
                <?php else: ?>
                    <?php foreach ($form_structure as $sectionIndex => $section): ?>
                        <div class="form-section" data-section-index="<?= $sectionIndex ?>">


                            <div class="mb-3">
                                <label for="sectionTitle_<?= $sectionIndex ?>" class="form-label">Section Title</label>
                                <input type="text" class="form-control" id="sectionTitle_<?= $sectionIndex ?>"
                                    name="sections[<?= $sectionIndex ?>][title]"
                                    value="<?= htmlspecialchars($section['title'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="sectionDescription_<?= $sectionIndex ?>" class="form-label">Section
                                    Description</label>
                                <textarea class="form-control" id="sectionDescription_<?= $sectionIndex ?>"
                                    name="sections[<?= $sectionIndex ?>][description]"><?= htmlspecialchars($section['description'] ?? '') ?></textarea>
                            </div>

                            <div class="fields-container" data-section-index="<?= $sectionIndex ?>">
                                <?php foreach ($section['fields'] as $fieldIndex => $field): ?>
                                    <div class="question-card" data-field-index="<?= $fieldIndex ?>">
                                        <div class="edit-controls">
                                            <button type="button" class="move-field-up" title="Move question up"><i
                                                    class="fas fa-arrow-up"></i></button>
                                            <button type="button" class="move-field-down" title="Move question down"><i
                                                    class="fas fa-arrow-down"></i></button>
                                            <button type="button" class="remove-field" title="Remove question"><i
                                                    class="fas fa-trash"></i></button>
                                        </div>

                                        <div class="mb-3">
                                            <label for="fieldLabel_<?= $sectionIndex ?>_<?= $fieldIndex ?>"
                                                class="form-label">Question Label</label>
                                            <input type="text" class="form-control"
                                                id="fieldLabel_<?= $sectionIndex ?>_<?= $fieldIndex ?>"
                                                name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][label]"
                                                value="<?= htmlspecialchars($field['label'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input"
                                                id="fieldRequired_<?= $sectionIndex ?>_<?= $fieldIndex ?>"
                                                name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][required]"
                                                <?= ($field['required'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label"
                                                for="fieldRequired_<?= $sectionIndex ?>_<?= $fieldIndex ?>">Required</label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Question Type</label>
                                            <select class="form-select field-type-selector"
                                                name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][type]">
                                                <option value="text" <?= $field['type'] === 'text' ? 'selected' : '' ?>>Text Answer
                                                </option>
                                                <option value="rating" <?= $field['type'] === 'rating' ? 'selected' : '' ?>>Star Rating
                                                </option>
                                                <option value="multiple_choice" <?= $field['type'] === 'multiple_choice' ? 'selected' : '' ?>>Multiple Choice</option>
                                                <option value="dish" <?= $field['type'] === 'dish' ? 'selected' : '' ?>>Dish Rating
                                                </option>
                                            </select>
                                        </div>

                                        <?php if ($field['type'] === 'multiple_choice'): ?>
                                            <div class="mb-3 options-container">
                                                <label class="form-label">Options (one per line)</label>
                                                <textarea class="form-control"
                                                    name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][options]"><?=
                                                            isset($field['options']) ? htmlspecialchars(is_array($field['options']) ? implode("\n", $field['options']) : $field['options']) : '' ?></textarea>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($field['type'] === 'dish'): ?>
                                            <div class="dish-ratings-container">
                                                <label class="form-label">Rating Categories</label>
                                                <?php foreach ($field['ratings'] as $ratingIndex => $rating): ?>
                                                    <div class="dish-rating mb-3" data-rating-index="<?= $ratingIndex ?>">
                                                        <div class="d-flex align-items-center">
                                                            <input type="text" class="form-control me-2"
                                                                name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][ratings][<?= $ratingIndex ?>][label]"
                                                                value="<?= htmlspecialchars($rating['label'] ?? '') ?>"
                                                                placeholder="Category label">
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input"
                                                                    name="sections[<?= $sectionIndex ?>][fields][<?= $fieldIndex ?>][ratings][<?= $ratingIndex ?>][required]"
                                                                    <?= ($rating['required'] ?? false) ? 'checked' : '' ?>>
                                                                <label class="form-check-label">Required</label>
                                                            </div>
                                                            <button type="button"
                                                                class="btn btn-sm btn-danger ms-2 remove-rating">Remove</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>


                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="text-center p-4">

                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Templates for dynamic elements -->
    <template id="sectionTemplate">
        <div class="form-section" data-section-index="__SECTION_INDEX__">
            <div class="edit-controls">
                <button type="button" class="move-section-up" title="Move section up"><i
                        class="fas fa-arrow-up"></i></button>
                <button type="button" class="move-section-down" title="Move section down"><i
                        class="fas fa-arrow-down"></i></button>
                <button type="button" class="remove-section" title="Remove section"><i
                        class="fas fa-trash"></i></button>
            </div>

            <div class="mb-3">
                <label for="sectionTitle___SECTION_INDEX__" class="form-label">Section Title</label>
                <input type="text" class="form-control" id="sectionTitle___SECTION_INDEX__"
                    name="sections[__SECTION_INDEX__][title]" value="">
            </div>

            <div class="mb-3">
                <label for="sectionDescription___SECTION_INDEX__" class="form-label">Section Description</label>
                <textarea class="form-control" id="sectionDescription___SECTION_INDEX__"
                    name="sections[__SECTION_INDEX__][description]"></textarea>
            </div>

            <div class="fields-container" data-section-index="__SECTION_INDEX__"></div>

            <button type="button" class="btn btn-primary add-question-btn" data-section-index="__SECTION_INDEX__">
                <i class="fas fa-plus"></i> Add Question
            </button>
            <div id="formSections">
                        <!-- Sections will be added here -->
                    </div>
        </div>
    </template>

    <template id="fieldTemplate">
        <div class="question-card" data-field-index="__FIELD_INDEX__">
            <div class="edit-controls">
                <button type="button" class="move-field-up" title="Move question up"><i
                        class="fas fa-arrow-up"></i></button>
                <button type="button" class="move-field-down" title="Move question down"><i
                        class="fas fa-arrow-down"></i></button>
                <button type="button" class="remove-field" title="Remove question"><i class="fas fa-trash"></i></button>
            </div>

            <div class="mb-3">
                <label for="fieldLabel___SECTION_INDEX_____FIELD_INDEX__" class="form-label">Question Label</label>
                <input type="text" class="form-control" id="fieldLabel___SECTION_INDEX_____FIELD_INDEX__"
                    name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][label]" value="">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="fieldRequired___SECTION_INDEX_____FIELD_INDEX__"
                    name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][required]">
                <label class="form-check-label" for="fieldRequired___SECTION_INDEX_____FIELD_INDEX__">Required</label>
            </div>

            <div class="mb-3">
                <label class="form-label">Question Type</label>
                <select class="form-select field-type-selector"
                    name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][type]">
                    <option value="text">Text Answer</option>
                    <option value="rating">Star Rating</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="dish">Dish Rating</option>
                </select>
            </div>
        </div>
    </template>

    <template id="optionsTemplate">
        <div class="mb-3 options-container">
            <label class="form-label">Options (one per line)</label>
            <textarea class="form-control"
                name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][options]"></textarea>
        </div>
    </template>

    <template id="dishRatingTemplate">
        <div class="dish-ratings-container">
            <label class="form-label">Rating Categories</label>
            <div class="dish-rating mb-3" data-rating-index="0">
                <div class="d-flex align-items-center">
                    <input type="text" class="form-control me-2"
                        name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][ratings][0][label]" value=""
                        placeholder="Category label">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input"
                            name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][ratings][0][required]" checked>
                        <label class="form-check-label">Required</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger ms-2 remove-rating">Remove</button>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-primary add-rating">
                <i class="fas fa-plus"></i> Add Rating Category
            </button>
        </div>
    </template>

    <template id="ratingCategoryTemplate">
        <div class="dish-rating mb-3" data-rating-index="__RATING_INDEX__">
            <div class="d-flex align-items-center">
                <input type="text" class="form-control me-2"
                    name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][ratings][__RATING_INDEX__][label]"
                    value="" placeholder="Category label">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input"
                        name="sections[__SECTION_INDEX__][fields][__FIELD_INDEX__][ratings][__RATING_INDEX__][required]"
                        checked>
                    <label class="form-check-label">Required</label>
                </div>
                <button type="button" class="btn btn-sm btn-danger ms-2 remove-rating">Remove</button>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        function setupDishRatingEvents(container) {
            // Add new rating category
            container.addEventListener('click', function (e) {
                if (e.target.classList.contains('add-rating') ||
                    e.target.closest('.add-rating')) {
                    const fieldCard = e.target.closest('.question-card');
                    const sectionIndex = fieldCard.closest('.form-section').dataset.sectionIndex;
                    const fieldIndex = fieldCard.dataset.fieldIndex;
                    const ratingsContainer = fieldCard.querySelector('.dish-ratings-container');

                    const ratingTemplate = document.getElementById('ratingCategoryTemplate').content.cloneNode(true);
                    const newIndex = ratingsContainer.querySelectorAll('.dish-rating').length;

                    let html = ratingTemplate.innerHTML;
                    html = html.replace(/__SECTION_INDEX__/g, sectionIndex)
                        .replace(/__FIELD_INDEX__/g, fieldIndex)
                        .replace(/__RATING_INDEX__/g, newIndex);

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    ratingsContainer.appendChild(tempDiv.firstChild);

                    updateIndices();
                }

                // Remove rating category
                if (e.target.classList.contains('remove-rating') ||
                    e.target.closest('.remove-rating')) {
                    if (confirm('Are you sure you want to remove this rating category?')) {
                        const ratingToRemove = e.target.closest('.dish-rating');
                        ratingToRemove.remove();
                        updateIndices();
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Add section
            document.querySelector('.add-section-btn').addEventListener('click', function () {
                const sectionTemplate = document.getElementById('sectionTemplate').content.cloneNode(true);
                const sections = document.querySelectorAll('.form-section');
                const newIndex = sections.length;

                let html = sectionTemplate.innerHTML;
                html = html.replace(/__SECTION_INDEX__/g, newIndex);

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                document.getElementById('formContent').appendChild(tempDiv.firstChild);
                updateIndices();
            });

            // Add question to section
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('add-question-btn') || e.target.closest('.add-question-btn')) {
                    const button = e.target.classList.contains('add-question-btn') ? e.target : e.target.closest('.add-question-btn');
                    const sectionIndex = button.dataset.sectionIndex;
                    const fieldsContainer = document.querySelector(`.fields-container[data-section-index="${sectionIndex}"]`);
                    const fields = fieldsContainer.querySelectorAll('.question-card');
                    const newIndex = fields.length;

                    const fieldTemplate = document.getElementById('fieldTemplate').content.cloneNode(true);
                    let html = fieldTemplate.innerHTML;
                    html = html.replace(/__SECTION_INDEX__/g, sectionIndex);
                    html = html.replace(/__FIELD_INDEX__/g, newIndex);

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    fieldsContainer.appendChild(tempDiv.firstChild);
                    updateIndices();
                }
            });

            // Handle question type changes
            document.addEventListener('change', function (e) {
                if (e.target.classList.contains('field-type-selector')) {
                    const fieldCard = e.target.closest('.question-card');
                    const sectionIndex = fieldCard.closest('.form-section').dataset.sectionIndex;
                    const fieldIndex = fieldCard.dataset.fieldIndex;
                    const type = e.target.value;

                    // Remove any existing type-specific fields
                    const optionsContainer = fieldCard.querySelector('.options-container');
                    if (optionsContainer) optionsContainer.remove();

                    const dishRatingsContainer = fieldCard.querySelector('.dish-ratings-container');
                    if (dishRatingsContainer) dishRatingsContainer.remove();

                    // Add type-specific fields
                    if (type === 'multiple_choice') {
                        const optionsTemplate = document.getElementById('optionsTemplate').content.cloneNode(true);
                        let html = optionsTemplate.innerHTML;
                        html = html.replace(/__SECTION_INDEX__/g, sectionIndex);
                        html = html.replace(/__FIELD_INDEX__/g, fieldIndex);

                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        fieldCard.appendChild(tempDiv.firstChild);
                    } else if (type === 'dish') {
                        const dishRatingTemplate = document.getElementById('dishRatingTemplate').content.cloneNode(true);
                        let html = dishRatingTemplate.innerHTML;
                        html = html.replace(/__SECTION_INDEX__/g, sectionIndex);
                        html = html.replace(/__FIELD_INDEX__/g, fieldIndex);

                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        fieldCard.appendChild(tempDiv.firstChild);

                        // Set up event listeners for the new dish rating elements
                        setupDishRatingEvents(fieldCard);
                    }
                } else if (type === 'dish') {
                    const dishRatingTemplate = document.getElementById('dishRatingTemplate').content.cloneNode(true);
                    let html = dishRatingTemplate.innerHTML;
                    html = html.replace(/__SECTION_INDEX__/g, sectionIndex);
                    html = html.replace(/__FIELD_INDEX__/g, fieldIndex);

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    fieldCard.appendChild(tempDiv.firstChild);

                    // Initialize events for the new dish rating container
                    const newDishContainer = fieldCard.querySelector('.dish-ratings-container');
                    setupDishRatingEvents(newDishContainer);
                }
            }
});

        // Add rating category to dish question
        function setupDishRatingEvents(container) {
            container.addEventListener('click', function (e) {
                if (e.target.classList.contains('add-rating') || e.target.closest('.add-rating')) {
                    const dishRatingsContainer = e.target.classList.contains('add-rating') ?
                        e.target.closest('.dish-ratings-container') :
                        e.target.closest('.add-rating').closest('.dish-ratings-container');

                    const ratings = dishRatingsContainer.querySelectorAll('.dish-rating');
                    const newIndex = ratings.length;

                    const ratingTemplate = document.getElementById('ratingCategoryTemplate').content.cloneNode(true);
                    let html = ratingTemplate.innerHTML;

                    // Find section and field indices
                    const fieldCard = dishRatingsContainer.closest('.question-card');
                    const sectionIndex = fieldCard.closest('.form-section').dataset.sectionIndex;
                    const fieldIndex = fieldCard.dataset.fieldIndex;

                    html = html.replace(/__SECTION_INDEX__/g, sectionIndex);
                    html = html.replace(/__FIELD_INDEX__/g, fieldIndex);
                    html = html.replace(/__RATING_INDEX__/g, newIndex);

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    // Insert before the add button
                    const addButton = dishRatingsContainer.querySelector('.add-rating');
                    dishRatingsContainer.insertBefore(tempDiv.firstChild, addButton);
                }

                if (e.target.classList.contains('remove-rating') || e.target.closest('.remove-rating')) {
                    const ratingToRemove = e.target.classList.contains('remove-rating') ?
                        e.target.closest('.dish-rating') :
                        e.target.closest('.remove-rating').closest('.dish-rating');
                    ratingToRemove.remove();
                    updateDishRatingIndices(ratingToRemove.closest('.dish-ratings-container'));
                }
            });
        }

        // Initialize dish rating events for existing elements
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize dish rating events for existing elements
            document.querySelectorAll('.dish-ratings-container').forEach(container => {
                setupDishRatingEvents(container);
            });

            // Remove section
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-section') || e.target.closest('.remove-section')) {
                    const sectionToRemove = e.target.classList.contains('remove-section') ?
                        e.target.closest('.form-section') :
                        e.target.closest('.remove-section').closest('.form-section');

                    if (confirm('Are you sure you want to remove this section and all its questions?')) {
                        sectionToRemove.remove();
                        updateIndices();
                    }
                }
            });

            // Remove question
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-field') || e.target.closest('.remove-field')) {
                    const fieldToRemove = e.target.classList.contains('remove-field') ?
                        e.target.closest('.question-card') :
                        e.target.closest('.remove-field').closest('.question-card');

                    if (confirm('Are you sure you want to remove this question?')) {
                        fieldToRemove.remove();
                        updateIndices();
                    }
                }
            });

            // Move section up
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('move-section-up') || e.target.closest('.move-section-up')) {
                    const section = e.target.classList.contains('move-section-up') ?
                        e.target.closest('.form-section') :
                        e.target.closest('.move-section-up').closest('.form-section');

                    const prevSection = section.previousElementSibling;
                    if (prevSection) {
                        section.parentNode.insertBefore(section, prevSection);
                        updateIndices();
                    }
                }
            });

            // Move section down
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('move-section-down') || e.target.closest('.move-section-down')) {
                    const section = e.target.classList.contains('move-section-down') ?
                        e.target.closest('.form-section') :
                        e.target.closest('.move-section-down').closest('.form-section');

                    const nextSection = section.nextElementSibling;
                    if (nextSection) {
                        section.parentNode.insertBefore(nextSection, section);
                        updateIndices();
                    }
                }
            });

            // Move question up
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('move-field-up') || e.target.closest('.move-field-up')) {
                    const field = e.target.classList.contains('move-field-up') ?
                        e.target.closest('.question-card') :
                        e.target.closest('.move-field-up').closest('.question-card');

                    const prevField = field.previousElementSibling;
                    if (prevField) {
                        field.parentNode.insertBefore(field, prevField);
                        updateIndices();
                    }
                }
            });

            // Move question down
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('move-field-down') || e.target.closest('.move-field-down')) {
                    const field = e.target.classList.contains('move-field-down') ?
                        e.target.closest('.question-card') :
                        e.target.closest('.move-field-down').closest('.question-card');

                    const nextField = field.nextElementSibling;
                    if (nextField) {
                        field.parentNode.insertBefore(nextField, field);
                        updateIndices();
                    }
                }
            });

            // Update dish rating indices
            function updateDishRatingIndices(container) {
                const ratings = container.querySelectorAll('.dish-rating');
                ratings.forEach((rating, index) => {
                    rating.dataset.ratingIndex = index;

                    // Update input names
                    const inputs = rating.querySelectorAll('input');
                    inputs.forEach(input => {
                        const name = input.name;
                        const newName = name.replace(/\[ratings\]\[\d+\]/, `[ratings][${index}]`);
                        input.name = newName;
                    });
                });
            }

            // Update all indices (sections and fields)
            function updateIndices() {
                // Update section indices
                const sections = document.querySelectorAll('.form-section');
                sections.forEach((section, sectionIndex) => {
                    section.dataset.sectionIndex = sectionIndex;

                    // Update section title and description inputs
                    const titleInput = section.querySelector('input[name^="sections["]');
                    const descriptionTextarea = section.querySelector('textarea[name^="sections["]');
                    const addQuestionBtn = section.querySelector('.add-question-btn');

                    if (titleInput) {
                        titleInput.name = `sections[${sectionIndex}][title]`;
                        titleInput.id = `sectionTitle_${sectionIndex}`;
                    }

                    if (descriptionTextarea) {
                        descriptionTextarea.name = `sections[${sectionIndex}][description]`;
                        descriptionTextarea.id = `sectionDescription_${sectionIndex}`;
                    }

                    if (addQuestionBtn) {
                        addQuestionBtn.dataset.sectionIndex = sectionIndex;
                    }

                    // Update fields container
                    const fieldsContainer = section.querySelector('.fields-container');
                    if (fieldsContainer) {
                        fieldsContainer.dataset.sectionIndex = sectionIndex;

                        // Update field indices
                        const fields = fieldsContainer.querySelectorAll('.question-card');
                        fields.forEach((field, fieldIndex) => {
                            field.dataset.fieldIndex = fieldIndex;

                            // Update field inputs
                            const fieldInputs = field.querySelectorAll('input, select, textarea');
                            fieldInputs.forEach(input => {
                                const name = input.name;
                                const newName = name.replace(/sections\[\d+\]/, `sections[${sectionIndex}]`)
                                    .replace(/fields\[\d+\]/, `fields[${fieldIndex}]`);
                                input.name = newName;

                                // Update IDs if they exist
                                if (input.id) {
                                    input.id = input.id.replace(/_\d+_/, `_${sectionIndex}_`)
                                        .replace(/_\d+$/, `_${fieldIndex}`);
                                }
                            });

                            // Update labels for field inputs
                            const labels = field.querySelectorAll('label');
                            labels.forEach(label => {
                                if (label.htmlFor) {
                                    label.htmlFor = label.htmlFor.replace(/_\d+_/, `_${sectionIndex}_`)
                                        .replace(/_\d+$/, `_${fieldIndex}`);
                                }
                            });

                            // Update dish rating indices if this is a dish question
                            const dishRatingsContainer = field.querySelector('.dish-ratings-container');
                            if (dishRatingsContainer) {
                                updateDishRatingIndices(dishRatingsContainer);
                            }
                        });
                    }
                });
            }

            // Form submission
            document.getElementById('editForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Build the form structure object
                const formStructure = [];
                const sections = document.querySelectorAll('.form-section');

                sections.forEach(section => {
                    const sectionData = {
                        title: section.querySelector('input[name$="[title]"]').value,
                        description: section.querySelector('textarea[name$="[description]"]').value,
                        fields: []
                    };

                    const fields = section.querySelectorAll('.question-card');
                    fields.forEach(field => {
                        const type = field.querySelector('.field-type-selector').value;
                        const fieldData = {
                            label: field.querySelector('input[name$="[label]"]').value,
                            required: field.querySelector('input[name$="[required]"]').checked,
                            type: type
                        };

                        if (type === 'multiple_choice') {
                            const optionsText = field.querySelector('textarea[name$="[options]"]').value;
                            fieldData.options = optionsText.split('\n').map(opt => opt.trim()).filter(opt => opt !== '');
                        } else if (type === 'dish') {
                            fieldData.ratings = [];
                            const ratings = field.querySelectorAll('.dish-rating');
                            ratings.forEach(rating => {
                                fieldData.ratings.push({
                                    label: rating.querySelector('input[name$="[label]"]').value,
                                    required: rating.querySelector('input[name$="[required]"]').checked
                                });
                            });
                        }

                        sectionData.fields.push(fieldData);
                    });

                    formStructure.push(sectionData);
                });

                // Set the JSON in the hidden input
                document.getElementById('formStructureInput').value = JSON.stringify(formStructure);

                // Submit the form
                this.submit();
            });
        });


    </script>
    <script>

    </script>
</body>

</html>
