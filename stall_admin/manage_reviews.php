<?php
require_once('../header-control.php');
session_start();
require_once('../config/db_config.php');
require_once("../jwt_validator.php");

// Validate JWT token
$decoded = validateToken("stall_admin_token", "../stall_admin/dashboard.php");
$id = $decoded->uid;
$username = $decoded->username;

// Safe local IP function
function getLocalIP()
{
    $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$sock) return "localhost"; // fallback
    @socket_connect($sock, "8.8.8.8", 53);
    @socket_getsockname($sock, $name);
    @socket_close($sock);
    return $name ?: "localhost";
}

$local_ip = getLocalIP();

// Get stall name
$stmt = $conn->prepare("SELECT stall_name FROM food_stalls WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stall = $result->fetch_assoc();
$stmt->close();

// Define stall_id for later use
$stall_id = $id;

// Handle deletion
if (isset($_POST["delete_form"])) {
    $form_id = intval($_POST["form_id"]);
    $stmt = $conn->prepare("DELETE FROM review_forms WHERE id = ? AND food_stall_id = ?");
    $stmt->bind_param("ii", $form_id, $stall_id);
    if ($stmt->execute()) {
        $_SESSION["success_message"] = "Review form deleted successfully!";
    } else {
        $_SESSION["error_message"] = "Error deleting form: " . $conn->error;
    }
    $stmt->close();
    header("Location: manage_reviews.php");
    exit();
}

// Get all review forms
$stmt = $conn->prepare("SELECT id, form_title, form_hash, is_active, created_at FROM review_forms WHERE food_stall_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $stall_id);
$stmt->execute();
$result = $stmt->get_result();
$review_forms = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - <?= htmlspecialchars($stall["stall_name"]) ?></title>
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
                <div>
                    <h2 class="dashboard-title"><i class="fas fa-clipboard-list me-2"></i> Manage Review Forms</h2>
                    <p class="mb-0"><?= htmlspecialchars($stall["stall_name"]) ?></p>
                </div>
            </div>

            <div class="p-4">
                <?php if (isset($_SESSION["success_message"])): ?>
                    <div class="alert alert-success"><?= $_SESSION["success_message"] ?></div>
                    <?php unset($_SESSION["success_message"]); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION["error_message"])): ?>
                    <div class="alert alert-danger"><?= $_SESSION["error_message"] ?></div>
                    <?php unset($_SESSION["error_message"]); ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Your Review Forms</h4>
                    <a href="create_review_form.php" class="btn btn-new-form">
                        <i class="fas fa-plus"></i> New Form
                    </a>
                </div>



                <?php if (count($review_forms) > 0): ?>
                    <div class="row">
                        <?php foreach ($review_forms as $form): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="form-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5><?= htmlspecialchars($form["form_title"]) ?></h5>
                                        <span
                                            class="form-status <?= $form["is_active"] ? "status-active" : "status-inactive" ?>">
                                            <?= $form["is_active"] ? "Active" : "Inactive" ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        Created: <?= date("M d, Y h:i A", strtotime($form["created_at"])) ?>
                                    </p>

                                    <div class="copy-link">
                                        <input type="text"
                                            value="<?= "http://{$local_ip}:8000/customer_review.php?form={$form["form_hash"]}" ?>"
                                            readonly>
                                        <button onclick="copyToClipboard(this)" title="Copy link">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>

                                    <div class="form-actions">
                                        <a href="stall_admin/preview_form.php" class="btn btn-sm btn-view"
                                            style="background:rgb(19, 206, 206); color: white;" data-bs-toggle="modal"
                                            data-bs-target="#previewModal" onclick="loadPreview('<?= $form["form_hash"] ?>')">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <button class="btn btn-sm" style="background: #6c5ce7; color: white;"
                                            onclick="generateQR('<?= htmlspecialchars($form["form_title"]) ?>', '<?= "http://{$local_ip}:8000/customer_review.php?form={$form["form_hash"]}" ?>')">
                                            <i class="fas fa-qrcode me-1"></i> QR Code
                                        </button>
                                        <a href="stall_admin/edit_form.php" class="btn btn-sm btn-view"
                                            style="background:rgb(253, 205, 12); color: white;" data-bs-toggle="modal"
                                            data-bs-target="#editModal" onclick="loadEdit('<?= $form["form_hash"] ?>')">
                                            <i class="fas fa-eye me-1"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-delete"
                                            style="background:rgb(231, 92, 92); color: white;"
                                            onclick="showDeleteModal('<?= $form["id"] ?>', '<?= htmlspecialchars($form["form_title"]) ?>')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h4>No Review Forms Yet</h4>
                        <p class="text-muted">You haven't created any review forms yet. Get started by creating your first
                            form.</p>
                        <a href="create_review_form.php" class="btn btn-new-form mt-3">
                            <i class="fas fa-plus me-2"></i> Create First Form
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Form Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="max-height: 80vh;">
                    <iframe id="previewFrame" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="max-height: 80vh;">
                    <iframe id="editFrame" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- QR Code Modal -->
    <div class="qr-container" id="qrContainer">
        <div class="qr-box">
            <span class="close-qr" onclick="closeQR()">&times;</span>
            <h4 id="qrTitle">QR Code</h4>
            <div id="qrcode" class="d-flex justify-content-center"></div>
            <div class="qr-actions">
                <button class="btn-qr" onclick="printQR()"><i class="fas fa-print"></i> Print</button>
                <button class="btn-qr" onclick="saveAsPDF()"><i class="fas fa-file-pdf"></i> Save as PDF</button>
            </div>
            <button class="close-modal-btn" onclick="closeQR()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--gradient-primary); color: white;">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p>Are you sure you want to delete this review form? This action cannot be undone.</p>
                    <p class="mb-0"><strong>Form Title:</strong> <span id="deleteFormTitle"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="form_id" id="deleteFormId">
                        <button type="submit" name="delete_form" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <script>

        function showDeleteModal(formId, formTitle) {
            document.getElementById('deleteFormId').value = formId;
            document.getElementById('deleteFormTitle').textContent = formTitle;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Preview Modal
document.getElementById('previewModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget; // Button that triggered the modal
    const formHash = button.getAttribute('onclick').match(/'([^']+)'/)[1];
    loadPreview(formHash);
});

// Edit Modal
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget; // Button that triggered the modal
    const formHash = button.getAttribute('onclick').match(/'([^']+)'/)[1];
    loadEdit(formHash);
});

function loadPreview(formHash) {
    const previewFrame = document.getElementById('previewFrame');
    previewFrame.src = `preview_form.php?form=${formHash}`;
    previewFrame.style.height = '400px';

    previewFrame.onload = function () {
        try {
            const bodyHeight = this.contentWindow.document.body.scrollHeight;
            const docHeight = this.contentWindow.document.documentElement.scrollHeight;
            const height = Math.max(bodyHeight, docHeight);

            const maxHeight = window.innerHeight * 0.8 - 120;
            this.style.height = Math.min(height, maxHeight) + 'px';
        } catch (error) {
            console.error('Error resizing iframe:', error);
            this.style.height = '600px';
        }
    };
}

function loadEdit(formHash) {
    const editFrame = document.getElementById('editFrame');
    editFrame.src = `edit_form.php?form=${formHash}`;
    editFrame.style.height = '400px';

    editFrame.onload = function () {
        try {
            const bodyHeight = this.contentWindow.document.body.scrollHeight;
            const docHeight = this.contentWindow.document.documentElement.scrollHeight;
            const height = Math.max(bodyHeight, docHeight);

            const maxHeight = window.innerHeight * 0.8 - 120;
            this.style.height = Math.min(height, maxHeight) + 'px';
        } catch (error) {
            console.error('Error resizing iframe:', error);
            this.style.height = '600px';
        }
    };
}

// Combined message listener for both preview and edit
window.addEventListener('message', (event) => {
    if (event.data === 'resize') {
        // Check which iframe needs resizing
        const previewFrame = document.getElementById('previewFrame');
        if (previewFrame.src.includes('form=')) {
            loadPreview(previewFrame.src.split('form=')[1]);
        }

        const editFrame = document.getElementById('editFrame');
        if (editFrame.src.includes('form=')) {
            loadEdit(editFrame.src.split('form=')[1]);
        }
    }
});

        function copyToClipboard(button) {
            const input = button.closest('.copy-link').querySelector('input');
            input.select();
            document.execCommand('copy');

            const icon = button.querySelector('i');
            icon.classList.remove('fa-copy');
            icon.classList.add('fa-check');

            setTimeout(() => {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            }, 2000);
        }

        function generateQR(title, url) {
            document.getElementById('qrcode').innerHTML = '';
            document.getElementById('qrTitle').textContent = title + ' - QR Code';

            new QRCode(document.getElementById('qrcode'), {
                text: url,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            document.getElementById('qrContainer').style.display = 'flex';

            // Add click event to QR code to open URL on mobile
            document.getElementById('qrcode').onclick = function () {
                window.open(url, '_blank');
            };
        }

        function closeQR() {
            document.getElementById('qrContainer').style.display = 'none';
        }

        function printQR() {
            // Get QR code image source (assuming it's an img element)
            const qrImg = document.querySelector('#qrContainer img');
            if (!qrImg) return;

            // Create a print stylesheet
            const style = document.createElement('style');
            style.id = 'print-styles';
            style.innerHTML = `
        @media print {
            body * {
                visibility: hidden;
            }
            #qrContainer, #qrContainer * {
                visibility: visible;
            }
            #qrContainer {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
            }
        }
    `;

            // Add to head
            document.head.appendChild(style);

            // Print
            window.print();

            // Clean up after printing is done
            setTimeout(() => {
                document.head.removeChild(style);
            }, 500);
        }

        function saveAsPDF() {
            const element = document.getElementById('qrContainer');
            const opt = {
                margin: 10,
                filename: 'qr_code.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>

</html>
