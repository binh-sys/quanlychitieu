// Admin Panel JavaScript

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    const sidebar = document.querySelector('.sidebar');
    const btnMenu = document.querySelector('.btn-menu');
    
    if (window.innerWidth <= 768 && 
        sidebar.classList.contains('open') && 
        !sidebar.contains(e.target) && 
        !btnMenu.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// Active nav item
const currentPath = window.location.pathname.split('/').pop() || 'index.php';
document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href');
    if (href === currentPath) {
        item.classList.add('active');
    } else {
        item.classList.remove('active');
    }
});
