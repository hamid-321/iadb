/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const iconOpen = document.getElementById('menu-icon-open');
    const iconClosed = document.getElementById('menu-icon-closed');

    if (menuButton) {
        menuButton.addEventListener('click', () => {
            // Toggle the 'hidden' class on the menu
            mobileMenu.classList.toggle('hidden');
            
            // Toggle the icons
            iconOpen.classList.toggle('hidden');
            iconOpen.classList.toggle('block');
            iconClosed.classList.toggle('hidden');
            iconClosed.classList.toggle('block');
        });
    }
});