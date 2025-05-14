<style>
    :root {
        --primary-color: #FF6B6B;
        --primary-dark: #e05a5a;
        --dark-color: #2D3436;
        --sidebar-width: 250px;
        --sidebar-collapsed-width: 80px;
    }

    .topbar {
        background: rgba(255, 107, 107, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: white;
        height: 70px;
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1000;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-left: 0;
    }

    .topbar-collapsed {
        left: var(--sidebar-collapsed-width);
    }

    /* Logo */
    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        font-size: 1.8rem;
        color: white;
    }

    .logo-text {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(to right, white, #f0f0f0);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: 0.5px;
    }

    /* Right Side Controls */
    .topbar-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    /* Search Bar */
    .search-container {
        position: relative;
        width: 240px;
    }

    .search-input {
        width: 100%;
        padding: 0.5rem 1rem 0.5rem 2.5rem;
        border-radius: 50px;
        border: none;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .search-input::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .search-input:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.25);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.8);
    }

    /* Notification */
    .notification-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.3rem;
        position: relative;
        padding: 0.5rem;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: white;
        color: var(--primary-color);
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    /* User Menu */
    .user-menu {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.3);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        font-weight: bold;
        font-size: 1.2rem;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.9rem;
        margin: 0;
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role {
        font-size: 0.75rem;
        opacity: 0.8;
        margin: 0;
    }

    .dropdown-menu {
        border: none;
        border-radius: 12px;
        padding: 0.5rem 0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        margin-top: 10px;
        background: white;
    }

    .dropdown-item {
        padding: 0.5rem 1.25rem;
        color: var(--dark-color);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.2s;
    }

    .dropdown-item:hover {
        background: var(--primary-color);
        color: white;
    }

    .dropdown-item i {
        width: 20px;
        text-align: center;
    }

    .dropdown-divider {
        margin: 0.25rem 0;
        border-color: rgba(0, 0, 0, 0.05);
    }

    /* Responsive styles */
    @media (max-width: 1200px) {
        .search-container {
            width: 180px;
        }
    }

    @media (max-width: 992px) {
        .topbar {
            left: var(--sidebar-collapsed-width);
            z-index: 1002;
        }

        .topbar-collapsed {
            left: 0;
        }

        .logo-text,
        .user-info {
            display: none;
        }

        .search-container {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .topbar {
            padding: 0 1rem;
        }
    }
</style>

<nav class="topbar" id="topbar">
    <div class="logo-container">
        <i class="bi bi-shop logo-icon"></i>
        <h1 class="logo-text">PLMUN Food Review</h1>
    </div>

    <div class="topbar-controls">
        <div class="dropdown">
            <div class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?php
                    // Get first letter of stall name
                    $stall_initial = !empty($_SESSION['stall_name']) ? strtoupper(substr($_SESSION['stall_name'], 0, 1)) : 'S';
                    echo $stall_initial;
                    ?>
                </div>
                <div class="user-info d-none d-xl-block">
                    <p class="user-name" title="<?= htmlspecialchars($_SESSION['stall_name'] ?? 'Stall') ?>">
                        <?= htmlspecialchars($_SESSION['stall_name'] ?? 'Stall') ?>
                    </p>
                    <p class="user-role">Manager</p>
                </div>
                <i class="bi bi-chevron-down d-none d-xl-block"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="account_management.php"><i class="bi bi-person"></i> Profile</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="../includes/logout.php"><i class="bi bi-box-arrow-right"></i>
                        Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Bootstrap JS (requires Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const topbar = document.getElementById('topbar');

        // Listen for sidebar toggle events
        document.addEventListener('sidebarToggle', function (e) {
            if (e.detail.collapsed) {
                topbar.classList.add('topbar-collapsed');
            } else {
                topbar.classList.remove('topbar-collapsed');
            }
        });

        // Add hover effect to user avatar
        const userAvatar = document.querySelector('.user-avatar');
        if (userAvatar) {
            userAvatar.addEventListener('mouseenter', function () {
                this.style.transform = 'scale(1.1)';
                this.style.boxShadow = '0 0 0 3px rgba(255, 255, 255, 0.3)';
            });

            userAvatar.addEventListener('mouseleave', function () {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        }

        // Initialize based on current state
        if (window.innerWidth <= 992) {
            topbar.classList.add('topbar-collapsed');
        }
    });
</script>