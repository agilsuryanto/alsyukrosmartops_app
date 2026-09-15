<?php
/**
 * Sidebar dibangun otomatis dari modul yang menjadi hak akses role yang login
 * (lihat getRoleModules() di config/auth.php, sumber: tabel hak_akses).
 */
function renderSidebar($currentPage = '') {
    $user = getCurrentUser();
    if (!$user) return;
    $modules = getRoleModules($user['role']);

    // Urutan tampil menu sesuai urutan modul pada dokumen (Bab 2.1 Model 1)
    $order = ['inventaris', 'karyawan', 'event', 'maintenance', 'arsip', 'pengajuan_unit', 'laporan'];
    usort($modules, fn($a, $b) => array_search($a, $order) <=> array_search($b, $order));
    ?>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo-badge"><img src="logo-icon.png" alt="Logo Perguruan Islam Al Syukro Universal"></div>
            <div class="brand-text">
                <strong>AL-SYUKROSMART</strong>
                <span>OPS PLATFORM</span>
            </div>
            <button class="sidebar-close" id="sidebarClose" onclick="closeSidebar()" aria-label="Tutup menu"><i data-lucide="x"></i></button>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
            </a>
            <?php foreach ($modules as $modul):
                $page = getModulePage($modul);
                if ($page === 'dashboard') continue;
                ?>
                <a href="dashboard.php?page=<?= urlencode($page) ?>" class="nav-item <?= $currentPage === $page ? 'active' : '' ?>">
                    <i data-lucide="<?= getModuleIcon($modul) ?>"></i><span><?= htmlspecialchars(getModuleLabel($modul)) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="dashboard.php?page=akun" class="nav-item <?= $currentPage === 'akun' ? 'active' : '' ?>">
                    <i data-lucide="user-cog"></i><span>Kelola Akun</span>
                </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar" style="background:<?= getRoleColor($user['role']) ?>"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                    <span><?= htmlspecialchars($user['nama_role']) ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn"><i data-lucide="log-out"></i> Keluar</a>
        </div>
    </aside>
    <?php
}
