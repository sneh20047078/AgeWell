// JavaScript to force center alignment on the hero section elements
document.addEventListener('DOMContentLoaded', function() {
    // Apply centering styles to hero section elements
    const applyStyles = () => {
        // Hero section
        const heroSection = document.querySelector('.hero-section');
        if (heroSection) {
            Object.assign(heroSection.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                textAlign: 'center'
            });
        }

        // Container
        const container = document.querySelector('.hero-section .container-fluid');
        if (container) {
            Object.assign(container.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                position: 'relative',
                zIndex: '10'
            });
        }

        // Row
        const row = document.querySelector('.hero-section .row');
        if (row) {
            Object.assign(row.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                textAlign: 'center'
            });
        }

        // Column
        const col = document.querySelector('.hero-section .col-12');
        if (col) {
            Object.assign(col.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                textAlign: 'center'
            });
        }

        // Brand logo
        const brandLogo = document.querySelector('.brand-logo');
        if (brandLogo) {
            Object.assign(brandLogo.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                textAlign: 'center'
            });
        }

        // Hero text content
        const heroText = document.querySelector('.hero-text-content');
        if (heroText) {
            Object.assign(heroText.style, {
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100%',
                textAlign: 'center',
                position: 'relative',
                zIndex: '5'
            });
        }

        // Headings and paragraphs
        const textElements = document.querySelectorAll('.hero-section h1, .hero-section h2, .hero-section p');
        textElements.forEach(element => {
            element.style.textAlign = 'center';
            element.style.width = '100%';
        });

        // Button group
        const btnGroup = document.querySelector('.btn-group-hero');
        if (btnGroup) {
            Object.assign(btnGroup.style, {
                display: 'flex',
                flexDirection: 'row',
                justifyContent: 'center',
                alignItems: 'center',
                width: '100%',
                textAlign: 'center'
            });
        }
    };

    // Apply immediately
    applyStyles();
    
    // Apply again after a delay to ensure it works after any other scripts
    setTimeout(applyStyles, 500);
    setTimeout(applyStyles, 1000);
}); 