/**
 * assets/js/hero-carousel.js
 * Homepage Hero Carousel behavior with full accessibility, state model, and progressive enhancement.
 */
(function() {
    // 1. Guard against duplicate initializations
    if (window.HeroCarouselInitialized) return;
    window.HeroCarouselInitialized = true;

    document.addEventListener('DOMContentLoaded', () => {
        const carousel = document.querySelector('.hero-carousel');
        if (!carousel) return;

        const slides = carousel.querySelectorAll('.hero-carousel__slide');
        const dots = carousel.querySelectorAll('.hero-carousel__dot');
        const prevBtn = carousel.querySelector('.hero-carousel__btn--prev');
        const nextBtn = carousel.querySelector('.hero-carousel__btn--next');
        
        if (slides.length <= 1) {
            // Static single slide, nothing to animate or control
            return;
        }

        // 2. Carousel State Model
        const state = {
            currentIndex: 0,
            timerId: null,
            isPointerHovered: false,
            isKeyboardFocused: false,
            isDocumentHidden: false,
            prefersReducedMotion: false,
            touchStartX: 0,
            touchStartY: 0
        };

        const intervalTime = 5000;

        // Check for reduced motion
        const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        state.prefersReducedMotion = motionQuery.matches;
        motionQuery.addEventListener('change', (e) => {
            state.prefersReducedMotion = e.matches;
            if (state.prefersReducedMotion) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        });

        // 3. Show Slide Function
        function showSlide(index) {
            // Remove active classes
            slides[state.currentIndex].classList.remove('hero-carousel__slide--active');
            dots[state.currentIndex].classList.remove('hero-carousel__dot--active');
            slides[state.currentIndex].setAttribute('aria-hidden', 'true');
            dots[state.currentIndex].setAttribute('aria-current', 'false');

            // Wrap index
            state.currentIndex = (index + slides.length) % slides.length;

            // Add active classes
            slides[state.currentIndex].classList.add('hero-carousel__slide--active');
            dots[state.currentIndex].classList.add('hero-carousel__dot--active');
            slides[state.currentIndex].setAttribute('aria-hidden', 'false');
            dots[state.currentIndex].setAttribute('aria-current', 'true');
        }

        function nextSlide() {
            showSlide(state.currentIndex + 1);
        }

        function prevSlide() {
            showSlide(state.currentIndex - 1);
        }

        // 4. Timer Controls
        function startAutoplay() {
            if (state.prefersReducedMotion) return;
            
            // Clear existing timer if any
            stopAutoplay();

            // Check all pause conditions
            if (state.isPointerHovered || state.isKeyboardFocused || state.isDocumentHidden) {
                return;
            }

            state.timerId = setInterval(nextSlide, intervalTime);
        }

        function stopAutoplay() {
            if (state.timerId) {
                clearInterval(state.timerId);
                state.timerId = null;
            }
        }

        function resetAutoplay() {
            stopAutoplay();
            startAutoplay();
        }

        // 5. Setup Action Listeners
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                nextSlide();
                resetAutoplay();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                prevSlide();
                resetAutoplay();
            });
        }

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', () => {
                showSlide(idx);
                resetAutoplay();
            });
        });

        // 6. Pause & Resume Conditions
        // Hover
        carousel.addEventListener('mouseenter', () => {
            state.isPointerHovered = true;
            stopAutoplay();
        });
        carousel.addEventListener('mouseleave', () => {
            state.isPointerHovered = false;
            startAutoplay();
        });

        // Focus
        carousel.addEventListener('focusin', () => {
            state.isKeyboardFocused = true;
            stopAutoplay();
        });
        carousel.addEventListener('focusout', () => {
            state.isKeyboardFocused = false;
            startAutoplay();
        });

        // Document Visibility
        document.addEventListener('visibilitychange', () => {
            state.isDocumentHidden = document.hidden;
            if (state.isDocumentHidden) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        });

        // Keyboard navigation (Left/Right Arrows, ignored inside inputs)
        carousel.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }

            if (e.key === 'ArrowRight' || e.key === 'Right') {
                nextSlide();
                resetAutoplay();
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
                prevSlide();
                resetAutoplay();
                e.preventDefault();
            }
        });

        // Touch Swipe (Min 50px, doesn't block vertical scrolling)
        carousel.addEventListener('touchstart', (e) => {
            state.touchStartX = e.touches[0].clientX;
            state.touchStartY = e.touches[0].clientY;
        }, { passive: true });

        carousel.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;  
            const diffX = touchEndX - state.touchStartX;
            const diffY = touchEndY - state.touchStartY;

            // Swipe threshold = 50px, and horizontal move must be strictly greater than vertical move
            if (Math.abs(diffX) > 50 && Math.abs(diffX) > Math.abs(diffY)) {
                if (diffX < 0) {
                    nextSlide(); // Swiped left -> next
                } else {
                    prevSlide(); // Swiped right -> prev
                }
                resetAutoplay();
            }
        }, { passive: true });

        // Initialize display and start loop
        showSlide(0);
        startAutoplay();
    });
})();
