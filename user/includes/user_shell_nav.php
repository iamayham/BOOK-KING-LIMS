<?php
declare(strict_types=1);
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isHome = $currentPage === 'user-dashboard.php';
$isBorrow = $currentPage === 'user-return-books.php' || $currentPage === 'user-return-book-confirm.php';
$isBrowse = $currentPage === 'user-borrow-books.php' || $currentPage === 'book-details.php' || $currentPage === 'user-borrow-confirm.php';
?>
<nav class="sidebar" aria-label="Main navigation">
    <div class="logo-container">
        <img src="../images/logo.png" alt="Book King Logo">
    </div>
    <div class="sidebar-item home<?= $isHome ? ' active' : '' ?>"
         onclick="window.location.href='user-dashboard.php'"
         role="button" tabindex="0" aria-label="Home"
         onkeydown="if(event.key==='Enter')window.location.href='user-dashboard.php'">
        <img src="../images/element-2 2.svg" alt="" class="icon-image">
        <span class="nav-label">Home</span>
    </div>
    <div class="sidebar-item list<?= $isBorrow ? ' active' : '' ?>"
         onclick="window.location.href='user-return-books.php'"
         role="button" tabindex="0" aria-label="My books"
         onkeydown="if(event.key==='Enter')window.location.href='user-return-books.php'">
        <img src="../images/Vector.svg" alt="" class="icon-image">
        <span class="nav-label">Borrow</span>
    </div>
    <div class="sidebar-item book<?= $isBrowse ? ' active' : '' ?>"
         onclick="window.location.href='user-borrow-books.php'"
         role="button" tabindex="0" aria-label="Browse books"
         onkeydown="if(event.key==='Enter')window.location.href='user-borrow-books.php'">
        <img src="../images/book.png" alt="" class="icon-image">
        <span class="nav-label">Browse</span>
    </div>
    <div class="sidebar-item logout"
         onclick="handleLogout()"
         role="button" tabindex="0" aria-label="Log out"
         onkeydown="if(event.key==='Enter')handleLogout()">
        <img src="../images/logout 3.png" alt="" class="icon-image">
        <span class="nav-label">Logout</span>
    </div>
</nav>
