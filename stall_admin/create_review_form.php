<?php

require_once('../header-control.php');

error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
require_once "../jwt_validator.php";
require_once "../config/db_config.php";

$decoded = validateToken("stall_admin_token", "../stall_admin/dashboard.php");
$id = $decoded->uid;
$username = $decoded->username;



// Fetch stall details
$stmt = $conn->prepare("SELECT stall_name FROM food_stalls WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stall = $result->fetch_assoc();
$stmt->close();

if (!$stall) {
    die("Stall not found");
}

$logo_path = "assets/img/{$stall_id}.jpg";
$default_logo = "assets/img/default.jpg";
$stall_logo = file_exists($logo_path) ? $logo_path : $default_logo;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_title = $_POST['form_title'] ?? 'Food Stall Review Form';
    $form_structure = json_encode($_POST['sections']);
    $form_hash = substr(md5(uniqid()), 0, 16);

    $stmt = $conn->prepare("INSERT INTO review_forms (food_stall_id, form_title, form_structure, form_hash) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $stall_id, $form_title, $form_structure, $form_hash);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Review form created successfully!";
        header("Location: manage_reviews.php");
        exit;
    } else {
        $error_message = "Error creating form: " . $conn->error;
    }
    $stmt->close();
}

$default_sections = [
    [
        'title' => 'Food Quality',
        'description' => 'Please rate our food dishes',
        'fields' => [
            [
                'type' => 'dish',
                'label' => 'Dish 1',
                'required' => true,
                'ratings' => [
                    ['label' => 'Taste', 'required' => true],
                    ['label' => 'Presentation', 'required' => true],
                    ['label' => 'Value for money', 'required' => true],
                    ['label' => 'Proportion', 'required' => true],
                    ['label' => 'Freshness', 'required' => true]
                ]
            ],
            [
                'type' => 'dish',
                'label' => 'Dish 2',
                'required' => true,
                'ratings' => [
                    ['label' => 'Taste', 'required' => true],
                    ['label' => 'Presentation', 'required' => true],
                    ['label' => 'Value for money', 'required' => true],
                    ['label' => 'Proportion', 'required' => true],
                    ['label' => 'Freshness', 'required' => true]
                ]
            ],
            [
                'type' => 'dish',
                'label' => 'Dish 3',
                'required' => true,
                'ratings' => [
                    ['label' => 'Taste', 'required' => true],
                    ['label' => 'Presentation', 'required' => true],
                    ['label' => 'Value for money', 'required' => true],
                    ['label' => 'Proportion', 'required' => true],
                    ['label' => 'Freshness', 'required' => true]
                ]
            ],

        ]
    ],
    [
        'title' => 'Service Ratings',
        'description' => 'Please rate our services',
        'fields' => [
            [
                'type' => 'rating',
                'label' => 'Staff Friendliness',
                'required' => true
            ],
            [
                'type' => 'rating',
                'label' => 'Service Speed',
                'required' => true
            ],
            [
                'type' => 'rating',
                'label' => 'Cleanliness',
                'required' => true
            ],
            [
                'type' => 'rating',
                'label' => 'Order Accuracy',
                'required' => true
            ],
            [
                'type' => 'rating',
                'label' => 'Overall Experience',
                'required' => true
            ]
        ]
    ],
    [
        'title' => 'Feedback',
        'description' => 'Please share your thoughts with us',
        'fields' => [
            [
                'type' => 'text',
                'label' => 'What did you enjoy most about your visit?',
                'required' => false
            ],
            [
                'type' => 'text',
                'label' => 'How can we improve our service?',
                'required' => false
            ],
            [
                'type' => 'text',
                'label' => 'Any additional comments?',
                'required' => false
            ]
        ]
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Review Form - <?= htmlspecialchars($stall['stall_name']) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../components/index.css">
</head>
<body>

    <!-- Include Sidebar -->
    <?php include('../includes/sidebar.php'); ?>
    <!-- Include Topbar -->
    <?php include('../includes/topbar.php'); ?>

    <div class="main-content">
        <div class="dashboard-card">
        <div class="dashboard-header d-flex justify-content-between align-items-center">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="dashboard-title"><i class="fas fa-edit me-2"></i> Create Review Form</h2>
                </div>
            </div>

            <div class="p-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>

                <div class="mb-4">
                    <h5 class="text-center"><strong>NOTE: </strong>This page is default form example, you can customize this page according to your needs.</h5>
                </div>

                <form id="reviewForm" method="POST">
                    <div class="mb-3">
                        <label for="form_title" class="form-label">Form Title</label>
                        <input type="text" class="form-control" id="form_title" name="form_title" value="Food Stall Review Form" required>
                    </div>

                    <div id="formSections">
                        <!-- Sections will be added here -->
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2 gap-md-0 mt-4">
    <button type="button" class="btn btn-add flex-grow-1 flex-md-grow-0" onclick="addSection()">
        <i class="fas fa-plus me-2"></i>Add Section
    </button>
    <button type="submit" class="btn btn-primary btn-lg flex-grow-1 flex-md-grow-0">
        <i class="fas fa-save me-2"></i>Save Form
    </button>
</div>
                </form>

                <div class="privacy-notice">
                    <p><i class="fas fa-info-circle me-2"></i> By creating this form, you agree to comply with the Data Privacy Act of 2012 (Republic Act No. 10173) of the Philippines.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates -->
    <template id="sectionTemplate">
        <div class="section-card">
            <div class="section-header d-flex justify-content-between align-items-center">
                <div>
                    <input type="text" class="form-control section-title-input mb-1" placeholder="Section Title" value="">
                    <input type="text" class="form-control section-desc-input small" placeholder="Section description (optional)" value="">
                </div>
                <button type="button" class="btn-remove" onclick="removeSection(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="section-fields">
                <!-- Fields will be added here -->
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addField(this, 'text')">
                <i class="fas fa-plus me-1"></i>Add Text Field
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addField(this, 'rating')">
                <i class="fas fa-star me-1"></i>Add Rating Field
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addField(this, 'dish')">
                <i class="fas fa-utensils me-1"></i>Add Dish Field
            </button>
        </div>
    </template>

    <template id="textFieldTemplate">
        <div class="field-item">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <input type="text" class="form-control field-label-input" placeholder="Field Label" value="">
                <button type="button" class="btn-remove" onclick="removeField(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" class="form-control" placeholder="Text input" disabled>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="required">
                <label class="form-check-label" for="required">Required</label>
            </div>
        </div>
    </template>

    <template id="ratingFieldTemplate">
        <div class="field-item">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <input type="text" class="form-control field-label-input" placeholder="Rating Label" value="">
                <button type="button" class="btn-remove" onclick="removeField(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="star-rating mb-2">
                <i class="far fa-star"></i>
                <i class="far fa-star"></i>
                <i class="far fa-star"></i>
                <i class="far fa-star"></i>
                <i class="far fa-star"></i>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="required">
                <label class="form-check-label" for="required">Required</label>
            </div>
        </div>
    </template>

    <template id="dishFieldTemplate">
        <div class="field-item">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <input type="text" class="form-control field-label-input" placeholder="Dish Name" value="">
                <button type="button" class="btn-remove" onclick="removeField(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" class="form-control mb-3" placeholder="Dish name" disabled>
            <div class="rating-options">
                <div class="rating-option mb-2">
                    <input type="text" class="form-control rating-label-input mb-1" placeholder="Rating Label" value="Taste">
                    <div class="star-rating">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
                <div class="rating-option mb-2">
                    <input type="text" class="form-control rating-label-input mb-1" placeholder="Rating Label" value="Presentation">
                    <div class="star-rating">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
                <div class="rating-option mb-2">
                    <input type="text" class="form-control rating-label-input mb-1" placeholder="Rating Label" value="Value for money">
                    <div class="star-rating">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
                <div class="rating-option mb-2">
                    <input type="text" class="form-control rating-label-input mb-1" placeholder="Rating Label" value="Proportion">
                    <div class="star-rating">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
                <div class="rating-option mb-2">
                    <input type="text" class="form-control rating-label-input mb-1" placeholder="Rating Label" value="Freshness">
                    <div class="star-rating">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="required" checked>
                <label class="form-check-label" for="required">Required</label>
            </div>
        </div>
    </template>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize form with default sections if empty
        document.addEventListener('DOMContentLoaded', function() {
            const defaultSections = <?php echo json_encode($default_sections); ?>;

            if (defaultSections.length > 0) {
                defaultSections.forEach(section => {
                    addSection(section);
                });
            } else {
                addSection();
            }
        });

        // Add a new section to the form
        function addSection(sectionData = null) {
            const template = document.getElementById('sectionTemplate');
            const clone = template.content.cloneNode(true);
            const sectionId = Date.now();

            const sectionElement = clone.querySelector('.section-card');
            sectionElement.dataset.sectionId = sectionId;

            const titleInput = clone.querySelector('.section-title-input');
            const descInput = clone.querySelector('.section-desc-input');

            if (sectionData) {
                titleInput.value = sectionData.title;
                descInput.value = sectionData.description;

                // Add fields if they exist in sectionData
                const fieldsContainer = clone.querySelector('.section-fields');
                sectionData.fields.forEach(field => {
                    addFieldToContainer(fieldsContainer, field.type, field);
                });
            }

            document.getElementById('formSections').appendChild(clone);
        }

        // Remove a section from the form
        function removeSection(button) {
            button.closest('.section-card').remove();
        }

        // Add a new field to a section
        function addField(button, fieldType, fieldData = null) {
            const section = button.closest('.section-card');
            const fieldsContainer = section.querySelector('.section-fields');

            addFieldToContainer(fieldsContainer, fieldType, fieldData);
        }

        // Add field to a specific container
        function addFieldToContainer(container, fieldType, fieldData = null) {
            let templateId;

            switch (fieldType) {
                case 'text':
                    templateId = 'textFieldTemplate';
                    break;
                case 'rating':
                    templateId = 'ratingFieldTemplate';
                    break;
                case 'dish':
                    templateId = 'dishFieldTemplate';
                    break;
                default:
                    return;
            }

            const template = document.getElementById(templateId);
            const clone = template.content.cloneNode(true);
            const fieldId = Date.now();

            const fieldElement = clone.querySelector('.field-item');
            fieldElement.dataset.fieldId = fieldId;
            fieldElement.dataset.fieldType = fieldType;

            const labelInput = clone.querySelector('.field-label-input');
            const requiredCheckbox = clone.querySelector('input[type="checkbox"]');

            if (fieldData) {
                if (labelInput) labelInput.value = fieldData.label;
                if (requiredCheckbox) requiredCheckbox.checked = fieldData.required;

                // Handle dish rating labels
                if (fieldType === 'dish' && fieldData.ratings) {
                    const ratingInputs = clone.querySelectorAll('.rating-label-input');
                    fieldData.ratings.forEach((rating, index) => {
                        if (ratingInputs[index]) {
                            ratingInputs[index].value = rating.label;
                        }
                    });
                }
            }

            container.appendChild(clone);
        }

        // Remove a field from a section
        function removeField(button) {
            button.closest('.field-item').remove();
        }

        // Prepare form data before submission
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                form_title: document.getElementById('form_title').value,
                sections: []
            };

            document.querySelectorAll('.section-card').forEach(sectionElement => {
                const section = {
                    title: sectionElement.querySelector('.section-title-input').value,
                    description: sectionElement.querySelector('.section-desc-input').value,
                    fields: []
                };

                sectionElement.querySelectorAll('.field-item').forEach(fieldElement => {
                    const fieldType = fieldElement.dataset.fieldType;
                    const field = {
                        type: fieldType,
                        label: fieldElement.querySelector('.field-label-input')?.value || '',
                        required: fieldElement.querySelector('input[type="checkbox"]').checked
                    };

                    if (fieldType === 'dish') {
                        field.ratings = [];
                        fieldElement.querySelectorAll('.rating-option').forEach(ratingOption => {
                            const labelInput = ratingOption.querySelector('.rating-label-input');
                            field.ratings.push({
                                label: labelInput ? labelInput.value : '',
                                required: true // Dish ratings are always required
                            });
                        });
                    }

                    section.fields.push(field);
                });

                formData.sections.push(section);
            });

            // Create hidden input with form structure
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'sections';
            input.value = JSON.stringify(formData.sections);
            this.appendChild(input);

            // Submit the form
            this.submit();
        });

        // Make section titles and descriptions editable
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('section-title') || e.target.classList.contains('section-description') ||
                e.target.classList.contains('field-label')) {
                makeEditable(e.target);
            }
        });

        function makeEditable(element) {
            const originalText = element.textContent;
            const placeholder = element.dataset.placeholder || '';

            element.innerHTML = '';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = originalText;
            input.placeholder = placeholder;
            input.className = 'form-control form-control-sm d-inline-block w-auto';

            element.appendChild(input);
            input.focus();

            input.addEventListener('blur', function() {
                element.textContent = input.value || placeholder;
            });

            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    element.textContent = input.value || placeholder;
                }
            });
        }
    </script>
</body>
</html>
