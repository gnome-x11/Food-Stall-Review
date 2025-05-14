<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLMUN Food Stall Review - Manager Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../components/index.css">


</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <span class="menu-text fs-5 fw-semibold">Menu</span>
            <button class="toggle-btn" id="toggleBtn">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="/canteen_reviews/stall_admin/dashboard.php" class="nav-link active"
                    data-bs-original-title="Dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/canteen_reviews/stall_admin/analytics.php" class="nav-link"
                    data-bs-original-title="Analytics">
                    <i class="bi bi-bar-chart-line"></i>
                    <span class="menu-text">Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/canteen_reviews/stall_admin/manage_reviews.php" class="nav-link"
                    data-bs-original-title="Manage Forms">
                    <i class="bi bi-files"></i>
                    <span class="menu-text">Manage Forms</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/canteen_reviews/stall_admin/create_review_form.php" class="nav-link"
                    data-bs-original-title="Create Review Form">
                    <i class="bi bi-file-earmark-plus"></i>
                    <span class="menu-text">Create Review Form</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/canteen_reviews/stall_admin/account_management.php" class="nav-link"
                    data-bs-original-title="Account Management">
                    <i class="bi bi-person-gear"></i>
                    <span class="menu-text">Account Management</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setActiveNavLink() {
            // Get the current page URL
            const currentUrl = window.location.pathname.split('/').pop() || 'stall_admin/dashboard.php';

            // Select all nav links
            const navLinks = document.querySelectorAll('.sidebar-menu .nav-link');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('active');
            });

            // Find and add active class to the matching link
            navLinks.forEach(link => {
                const linkUrl = link.getAttribute('href').split('/').pop();
                if (currentUrl === linkUrl) {
                    link.classList.add('active');
                }
            });

            // Fallback for dashboard if no match found
            if (!document.querySelector('.sidebar-menu .nav-link.active')) {
                const dashboardLink = document.querySelector('.sidebar-menu .nav-link[href*="dashboard"]');
                if (dashboardLink) {
                    dashboardLink.classList.add('active');
                }
            }
        }// Call the function when the page loads
        document.addEventListener('DOMContentLoaded', function () {
            setActiveNavLink();

            // Rest of your existing DOMContentLoaded code...
        });


        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleBtn');

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-original-title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'manual'
                });
            });

            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('sidebar-collapsed');

                if (sidebar.classList.contains('sidebar-collapsed')) {
                    toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                } else {
                    toggleBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                }

                // Dispatch custom event for other components to listen to
                document.dispatchEvent(new CustomEvent('sidebarToggle', {
                    detail: { collapsed: sidebar.classList.contains('sidebar-collapsed') }
                }));
            });

            // Auto-collapse on mobile
            function handleResize() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.add('sidebar-collapsed');
                    toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                    toggleBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize(); // Initialize
        });
    </script>
</body>

</html>