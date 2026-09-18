// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
    // 1. Sticky Header
    const header = document.getElementById('mainHeader');
    if (header) {
        let isTicking = false;
        window.addEventListener('scroll', () => {
            if (!isTicking) {
                window.requestAnimationFrame(() => {
                    if (window.scrollY > 20) {
                        header.classList.add('scrolled');
                    } else {
                        header.classList.remove('scrolled');
                    }
                    isTicking = false;
                });
                isTicking = true;
            }
        }, { passive: true });
    }

    // 2. Mobile Nav Menu Toggle
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });

        // Close mobile menu when clicking navigation links or settings links
        const navItems = navLinks.querySelectorAll('a');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navLinks.classList.remove('open');
            });
        });
    }

    // Theme Toggle Logic
    const themeToggles = document.querySelectorAll('.theme-toggle');

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        themeToggles.forEach(button => {
            const icon = button.querySelector('.themeIcon');
            if (icon) {
                const newIcon = document.createElement('i');
                newIcon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
                newIcon.className = icon.className;
                icon.parentNode.replaceChild(newIcon, icon);
            }

            // Update ARIA accessibility labels
            button.setAttribute(
                'aria-label',
                theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'
            );

            // Update mobile menu setting row text label dynamically if present
            const label = button.querySelector('.mobile-setting-label');
            if (label) {
                const isDark = theme === 'dark';
                const isRTL = document.documentElement.dir === 'rtl' || document.dir === 'rtl';
                if (isRTL) {
                    label.textContent = isDark ? 'الوضع المضيء' : 'الوضع الداكن';
                } else {
                    label.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                }
            }
        });

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    // Initialize theme state
    const currentTheme = localStorage.getItem('theme') || 'light';
    applyTheme(currentTheme);

    themeToggles.forEach(button => {
        button.addEventListener('click', (e) => {
            // Theme switching must not close the menu or trigger form events
            e.stopPropagation();
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    });

    // 3. Product Details Quantity Spinner
    const qtyInput = document.querySelector('.qty-input');
    const btnMinus = document.querySelector('.qty-minus');
    const btnPlus = document.querySelector('.qty-plus');

    if (qtyInput && btnMinus && btnPlus) {
        const maxStock = parseInt(qtyInput.getAttribute('max')) || 99;
        
        btnMinus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value) || 1;
            if (val > 1) {
                qtyInput.value = val - 1;
            }
        });

        btnPlus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value) || 1;
            if (val < maxStock) {
                qtyInput.value = val + 1;
            }
        });

        qtyInput.addEventListener('change', () => {
            let val = parseInt(qtyInput.value) || 1;
            if (val < 1) qtyInput.value = 1;
            if (val > maxStock) qtyInput.value = maxStock;
        });
    }

    // 4. Scroll Reveal Observer
    const revealElements = document.querySelectorAll('.reveal-on-scroll');
    if ('IntersectionObserver' in window && revealElements.length > 0) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });
        
        revealElements.forEach(el => revealObserver.observe(el));
    } else {
        revealElements.forEach(el => el.classList.add('revealed'));
    }

    // 5. FAQ Accordion Handler
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const trigger = item.querySelector('.faq-trigger');
        const content = item.querySelector('.faq-content');
        if (trigger && content) {
            trigger.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                
                // Close all other active FAQ items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                        otherItem.querySelector('.faq-content').style.maxHeight = null;
                    }
                });
                
                // Toggle current FAQ item
                if (isActive) {
                    item.classList.remove('active');
                    content.style.maxHeight = null;
                } else {
                    item.classList.add('active');
                    content.style.maxHeight = content.scrollHeight + 'px';
                }
            });
        }
    });

    // 6. Interactive Number Counter Animation for Statistics
    const statCounters = document.querySelectorAll('.stat-count');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    if (statCounters.length > 0) {
        const startCounterAnimation = (element) => {
            const target = parseInt(element.getAttribute('data-target')) || 0;
            if (prefersReducedMotion) {
                element.textContent = target.toLocaleString();
                return;
            }
            
            const duration = 1500; // Animation duration in ms
            const frameRate = 1000 / 60; // 60fps
            const totalFrames = Math.round(duration / frameRate);
            let frame = 0;
            
            const animate = () => {
                frame++;
                const progress = frame / totalFrames;
                // Easing out function
                const easeOutQuad = progress * (2 - progress);
                const currentVal = Math.round(target * easeOutQuad);
                
                element.textContent = currentVal.toLocaleString();
                
                if (frame < totalFrames) {
                    requestAnimationFrame(animate);
                } else {
                    element.textContent = target.toLocaleString();
                }
            };
            requestAnimationFrame(animate);
        };

        if ('IntersectionObserver' in window) {
            const statsObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        startCounterAnimation(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.2
            });
            statCounters.forEach(el => statsObserver.observe(el));
        } else {
            statCounters.forEach(el => {
                const target = el.getAttribute('data-target') || '0';
                el.textContent = parseInt(target).toLocaleString();
            });
        }
    }

    // 7. Expandable Floating Action Button (FAB) Toggle Logic
    const fabMainTrigger = document.getElementById('fabMainTrigger');
    const fabMobileContainer = document.getElementById('fabMobileContainer');
    
    if (fabMainTrigger && fabMobileContainer) {
        const chatIcon = fabMainTrigger.querySelector('.trigger-icon-chat');
        const closeIcon = fabMainTrigger.querySelector('.trigger-icon-close');
        
        fabMainTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isExpanded = fabMobileContainer.classList.toggle('expanded');
            
            if (isExpanded) {
                if (chatIcon) chatIcon.style.display = 'none';
                if (closeIcon) closeIcon.style.display = 'block';
            } else {
                if (chatIcon) chatIcon.style.display = 'block';
                if (closeIcon) closeIcon.style.display = 'none';
            }
        });
        
        // Close FAB when clicking outside
        document.addEventListener('click', (e) => {
            if (!fabMobileContainer.contains(e.target)) {
                fabMobileContainer.classList.remove('expanded');
                if (chatIcon) chatIcon.style.display = 'block';
                if (closeIcon) closeIcon.style.display = 'none';
            }
        });
    }

    // 8. Before/After Comparison Image Slider Drag Logic
    const baSlider = document.getElementById('baSlider');
    const baAfterWrapper = document.getElementById('baAfterWrapper');
    const baHandle = document.getElementById('baHandle');
    
    if (baSlider && baAfterWrapper && baHandle) {
        const isRTL = document.documentElement.dir === 'rtl' || document.dir === 'rtl';
        
        const adjustSlider = (clientX) => {
            const rect = baSlider.getBoundingClientRect();
            const positionX = clientX - rect.left;
            let percentage = (positionX / rect.width) * 100;
            
            // Constrain between 0% and 100%
            if (percentage < 0) percentage = 0;
            if (percentage > 100) percentage = 100;
            
            // Adjust overlay width & handle position based on RTL/LTR
            if (isRTL) {
                const rtlPercentage = 100 - percentage;
                baAfterWrapper.style.width = `${rtlPercentage}%`;
                baHandle.style.right = `${rtlPercentage}%`;
                baHandle.style.left = 'auto';
            } else {
                baAfterWrapper.style.width = `${percentage}%`;
                baHandle.style.left = `${percentage}%`;
                baHandle.style.right = 'auto';
            }
        };
        
        const onPointerMove = (e) => {
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            adjustSlider(clientX);
        };
        
        const onPointerUp = () => {
            window.removeEventListener('mousemove', onPointerMove);
            window.removeEventListener('mouseup', onPointerUp);
            window.removeEventListener('touchmove', onPointerMove);
            window.removeEventListener('touchend', onPointerUp);
        };
        
        baHandle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            window.addEventListener('mousemove', onPointerMove);
            window.addEventListener('mouseup', onPointerUp);
        });
        
        baHandle.addEventListener('touchstart', (e) => {
            window.addEventListener('touchmove', onPointerMove);
            window.addEventListener('touchend', onPointerUp);
        });
        
        // Allow clicking anywhere on the slider container to move the handle
        baSlider.addEventListener('click', (e) => {
            if (e.target !== baHandle && !baHandle.contains(e.target)) {
                adjustSlider(e.clientX);
            }
        });
    }

    // 9. Project Detail Gallery Thumbnail Switcher
    const galleryThumbs = document.querySelectorAll('.gallery-thumb-btn');
    const primaryImg = document.getElementById('primaryGalleryImg');
    
    if (galleryThumbs.length > 0 && primaryImg) {
        galleryThumbs.forEach(thumb => {
            thumb.addEventListener('click', () => {
                const targetSrc = thumb.getAttribute('data-large-src');
                const targetSrcset = thumb.getAttribute('data-large-srcset') || '';
                
                if (targetSrc) {
                    // Smooth transition animation
                    primaryImg.style.opacity = '0.3';
                    
                    setTimeout(() => {
                        primaryImg.setAttribute('src', targetSrc);
                        if (targetSrcset) {
                            primaryImg.setAttribute('srcset', targetSrcset);
                        } else {
                            primaryImg.removeAttribute('srcset');
                        }
                        primaryImg.style.opacity = '1';
                    }, 120);
                    
                    // Update active classes
                    galleryThumbs.forEach(t => t.classList.remove('active'));
                    thumb.classList.add('active');
                }
            });
        });
    }

    // 10. Portfolio Filtering Logic
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card-item');
    
    if (filterButtons.length > 0 && projectCards.length > 0) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const filterValue = btn.getAttribute('data-filter');
                
                // Toggle active class on buttons
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show/hide project cards with opacity transitions
                projectCards.forEach(card => {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        if (filterValue === 'all' || card.getAttribute('data-category') === filterValue) {
                            card.style.display = 'flex';
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'scale(1)';
                            }, 50);
                        } else {
                            card.style.display = 'none';
                        }
                    }, 200);
                });
            });
        });
    }
});


