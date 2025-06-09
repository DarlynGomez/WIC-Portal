// Ensures elements are available after file is dynamically loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get all elements
    const hamburgerMenu = document.getElementById('hamburger-menu');
    const navSidebar = document.getElementById('nav-sidebar');
    const closeNavBtn = document.querySelector('.close-nav-btn');
    const signInBtn = document.getElementById('sign-in-btn');
    const becomeMemberBtn = document.getElementById('become-member-btn');
    const heroMemberBtn = document.getElementById('hero-member-btn');
    const signupLink = document.getElementById('sign-up');

    const overlay = document.getElementById('overlay');
    const closeButtons = document.querySelectorAll('.close-btn');

    const forgotPasswordLink = document.getElementById('forgot-password-link');
    const registerLink = document.getElementById('register-link');
    const signInLink = document.getElementById('signin-link');
    
    const loginSidebar = document.getElementById('login-sidebar');
    const signupSidebar = document.getElementById('signup-sidebar');
    const forgotSidebar = document.getElementById('forgot-sidebar');

    const emplid = document.getElementById('emplid');
    const togglePassword = document.getElementById('togglePassword');
    const toggleEmplid = document.getElementById('toggleEmplid');
    const passwordBox = document.getElementById('passwordBox');

    // Navigation sidebar functions
    function openNavSidebar() {
        navSidebar.classList.add('show');
        overlay.style.display = 'block';
    }

    function closeNavSidebar() {
        navSidebar.classList.remove('show');
        if (!loginSidebar?.classList.contains('show') && 
            !signupSidebar?.classList.contains('show') && 
            !forgotSidebar?.classList.contains('show')) {
            overlay.style.display = 'none';
        }
    }

    // Event listeners for navigation sidebar
    if (hamburgerMenu) {
        hamburgerMenu.addEventListener('click', openNavSidebar);
    }

    if (closeNavBtn) {
        closeNavBtn.addEventListener('click', closeNavSidebar);
    }

    
    // Opens specified sidebar and disable scrolling
    function openSideBar(sidebar) {
        // Close all other sidebars first
        closeAllSideBars();
        
        if (sidebar) {
            sidebar.classList.add('show');
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Disables scrolling
        }
    }


    // Close all sidebars and re-enable scrolling
    function closeAllSideBars() {
        // Close navigation sidebar
        if (navSidebar) navSidebar.classList.remove('show');
        
        // Close login/signup sidebars
        if (loginSidebar) loginSidebar.classList.remove('show');
        if (signupSidebar) signupSidebar.classList.remove('show');
        if (forgotSidebar) forgotSidebar.classList.remove('show');
        
        // Hide overlay
        if (overlay) {
            overlay.style.display = 'none';
        }
        
        document.body.style.overflow = 'auto';    // Re-enable scrolling
    }
    
    // Add event listeners
    if (signInBtn) {
        signInBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(loginSidebar);
        });
    }

    if (becomeMemberBtn) {
        becomeMemberBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(signupSidebar);
        });
    }

    if (heroMemberBtn) {
        heroMemberBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(signupSidebar);
        });
    }

    if (signupLink) {
        signupLink.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(signupSidebar);
        });
    }

    if (signInLink) {
        signInLink.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(loginSidebar);
        });
    }
    
    
    if (registerLink) {
        registerLink.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(signupSidebar);
        });
    }
    
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            openSideBar(forgotSidebar);
        });
    }
    
    // Close sidebar when close button is clicked
    closeButtons.forEach(function(button) {
        button.addEventListener('click', closeAllSideBars);
    });
    
    // Close sidebar when overlay is clicked
    if (overlay) {
        overlay.addEventListener('click', function() {
            closeAllSideBars();
        });
    }

    // Shows and hides password
    if (togglePassword && passwordBox) {
        togglePassword.addEventListener('click', function() {
            let isPassword = passwordBox.type === 'password';
            passwordBox.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? 'HIDE' : 'SHOW';
        });
    }

    // Shows and hides EMPLID
    if (toggleEmplid && emplid) {
        toggleEmplid.addEventListener('click', function() {
            let isEmplid = emplid.type === 'password';
            emplid.type = isEmplid ? 'text' : 'password';
            this.textContent = isEmplid ? 'HIDE' : 'SHOW';
        });
    }

});