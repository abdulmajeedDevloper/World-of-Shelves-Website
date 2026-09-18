/**
 * Product Details Gallery, Variation Selector & Accessible Lightbox System
 * World of Shelves — Sprint V3
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const galleryContainer = document.querySelector('.detail-img-card-wrapper');
        if (!galleryContainer) return;

        // Extract gallery payload embedded in page JSON script tag if available
        let galleryData = { images: [], colors: [], models: [] };
        const dataScript = document.getElementById('product-gallery-data');
        if (dataScript) {
            try {
                galleryData = JSON.parse(dataScript.textContent);
            } catch (e) {
                console.warn('Failed to parse product gallery data JSON:', e);
            }
        }

        const mainImage = document.getElementById('mainDetailsImage');
        const mainImageBtn = document.getElementById('mainImageLightboxBtn');
        const thumbs = document.querySelectorAll('.thumbnail-item');
        const colorSwatches = document.querySelectorAll('.color-swatch-item');
        const modelBtns = document.querySelectorAll('.model-btn-item');
        const liveRegion = document.getElementById('gallery-live-region');

        let selectedColorId = null;
        let selectedModelId = null;

        // ─── 1. THUMBNAIL SWITCHING ───
        thumbs.forEach(thumb => {
            thumb.addEventListener('click', function() {
                const imgUrl = this.getAttribute('data-src');
                const altText = this.getAttribute('data-alt') || (mainImage ? mainImage.alt : '');
                const colorId = this.getAttribute('data-color-id');
                const modelId = this.getAttribute('data-model-id');

                updateMainImage(imgUrl, altText);
                setActiveThumb(this);

                if (colorId) highlightColor(colorId);
                if (modelId) highlightModel(modelId);
            });

            thumb.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // ─── 2. COLOR SWATCH SELECTION ───
        colorSwatches.forEach(swatch => {
            swatch.addEventListener('click', function() {
                const colorId = parseInt(this.getAttribute('data-color-id'), 10);
                const colorName = this.getAttribute('data-color-name');
                const colorImg = this.getAttribute('data-color-img');

                selectedColorId = colorId;

                // Update active swatch state
                colorSwatches.forEach(s => {
                    s.classList.remove('active');
                    s.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');

                // Determine best image using fallback hierarchy
                const targetImage = resolveFallbackImage(selectedColorId, selectedModelId, colorImg);
                if (targetImage) {
                    updateMainImage(targetImage.url, targetImage.alt);
                    syncThumbByUrl(targetImage.url);
                }

                announceLiveRegion(colorName ? ('تم اختيار اللون: ' + colorName) : 'Color updated');
            });
        });

        // ─── 3. MODEL SELECTION ───
        modelBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const modelId = parseInt(this.getAttribute('data-model-id'), 10);
                const modelName = this.getAttribute('data-model-name');
                const modelImg = this.getAttribute('data-model-img');

                selectedModelId = modelId;

                // Update active model state
                modelBtns.forEach(m => {
                    m.classList.remove('active');
                    m.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');

                // Determine best image using fallback hierarchy
                const targetImage = resolveFallbackImage(selectedColorId, selectedModelId, modelImg);
                if (targetImage) {
                    updateMainImage(targetImage.url, targetImage.alt);
                    syncThumbByUrl(targetImage.url);
                }

                announceLiveRegion(modelName ? ('تم اختيار الموديل: ' + modelName) : 'Model updated');
            });
        });

        // ─── 4. FALLBACK HIERARCHY RESOLUTION ───
        function resolveFallbackImage(colorId, modelId, specificImgPath) {
            const images = galleryData.images || [];

            // 1. Exact color + model match
            if (colorId && modelId) {
                const match = images.find(img => img.color_id === colorId && img.model_id === modelId);
                if (match) return match;
            }

            // 2. Selected color image match
            if (colorId) {
                const match = images.find(img => img.color_id === colorId);
                if (match) return match;
            }

            // 3. Selected model image match
            if (modelId) {
                const match = images.find(img => img.model_id === modelId);
                if (match) return match;
            }

            // 4. Specific color/model direct image_path attribute on swatch/btn
            if (specificImgPath) {
                return { url: specificImgPath, alt: mainImage ? mainImage.alt : '' };
            }

            // 5. Primary image or first image
            const primary = images.find(img => img.is_primary) || images[0];
            if (primary) return primary;

            // 6. Current main image as fallback
            return mainImage ? { url: mainImage.src, alt: mainImage.alt } : null;
        }

        // ─── HELPER FUNCTIONS ───
        function updateMainImage(src, alt) {
            if (!mainImage || !src) return;
            mainImage.style.opacity = '0.4';
            setTimeout(() => {
                mainImage.src = src;
                if (alt) mainImage.alt = alt;
                mainImage.style.opacity = '1';
            }, 100);
        }

        function setActiveThumb(targetThumb) {
            thumbs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            if (targetThumb) {
                targetThumb.classList.add('active');
                targetThumb.setAttribute('aria-selected', 'true');
            }
        }

        function syncThumbByUrl(url) {
            if (!url) return;
            let matched = false;
            thumbs.forEach(t => {
                const src = t.getAttribute('data-src');
                if (src && (src === url || url.endsWith(src) || src.endsWith(url))) {
                    setActiveThumb(t);
                    matched = true;
                }
            });
            if (!matched && thumbs.length > 0) {
                setActiveThumb(thumbs[0]);
            }
        }

        function highlightColor(colorId) {
            colorSwatches.forEach(s => {
                const id = s.getAttribute('data-color-id');
                if (id === String(colorId)) {
                    s.classList.add('active');
                    s.setAttribute('aria-selected', 'true');
                } else {
                    s.classList.remove('active');
                    s.setAttribute('aria-selected', 'false');
                }
            });
        }

        function highlightModel(modelId) {
            modelBtns.forEach(m => {
                const id = m.getAttribute('data-model-id');
                if (id === String(modelId)) {
                    m.classList.add('active');
                    m.setAttribute('aria-selected', 'true');
                } else {
                    m.classList.remove('active');
                    m.setAttribute('aria-selected', 'false');
                }
            });
        }

        function announceLiveRegion(msg) {
            if (liveRegion) {
                liveRegion.textContent = msg;
            }
        }

        // ─── 5. ACCESSIBLE LIGHTBOX ───
        const lightbox = document.getElementById('productLightboxModal');
        if (lightbox && mainImageBtn) {
            const lightboxImg = document.getElementById('lightboxMainImg');
            const closeBtn = document.getElementById('lightboxCloseBtn');
            const prevBtn = document.getElementById('lightboxPrevBtn');
            const nextBtn = document.getElementById('lightboxNextBtn');
            let triggerElement = null;
            let currentIndex = 0;

            function getAllImages() {
                const list = [];
                thumbs.forEach(t => {
                    const src = t.getAttribute('data-src');
                    const alt = t.getAttribute('data-alt') || '';
                    if (src && !list.some(i => i.src === src)) {
                        list.push({ src: src, alt: alt });
                    }
                });
                if (list.length === 0 && mainImage) {
                    list.push({ src: mainImage.src, alt: mainImage.alt });
                }
                return list;
            }

            function openLightbox(index) {
                const imageList = getAllImages();
                if (imageList.length === 0) return;

                currentIndex = index >= 0 && index < imageList.length ? index : 0;
                triggerElement = document.activeElement;

                lightboxImg.src = imageList[currentIndex].src;
                lightboxImg.alt = imageList[currentIndex].alt;
                lightbox.style.display = 'flex';
                lightbox.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                if (closeBtn) closeBtn.focus();
            }

            function closeLightbox() {
                lightbox.style.display = 'none';
                lightbox.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                if (triggerElement && typeof triggerElement.focus === 'function') {
                    triggerElement.focus();
                }
            }

            function showPrev() {
                const imageList = getAllImages();
                if (imageList.length <= 1) return;
                currentIndex = (currentIndex - 1 + imageList.length) % imageList.length;
                lightboxImg.src = imageList[currentIndex].src;
                lightboxImg.alt = imageList[currentIndex].alt;
            }

            function showNext() {
                const imageList = getAllImages();
                if (imageList.length <= 1) return;
                currentIndex = (currentIndex + 1) % imageList.length;
                lightboxImg.src = imageList[currentIndex].src;
                lightboxImg.alt = imageList[currentIndex].alt;
            }

            mainImageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const currentSrc = mainImage ? mainImage.src : '';
                const imageList = getAllImages();
                let idx = imageList.findIndex(i => i.src === currentSrc || currentSrc.endsWith(i.src));
                if (idx === -1) idx = 0;
                openLightbox(idx);
            });

            if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
            if (prevBtn) prevBtn.addEventListener('click', showPrev);
            if (nextBtn) nextBtn.addEventListener('click', showNext);

            // Lightbox Overlay Backdrop click
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    closeLightbox();
                }
            });

            // Keyboard navigation (Escape, Left Arrow, Right Arrow) & Focus trap
            lightbox.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeLightbox();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    document.dir === 'rtl' ? showNext() : showPrev();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    document.dir === 'rtl' ? showPrev() : showNext();
                } else if (e.key === 'Tab') {
                    // Focus Trap
                    const focusables = lightbox.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([-tabindex="-1"])');
                    if (focusables.length === 0) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];

                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });

            // Mobile Touch Swipe Support
            let touchStartX = 0;
            lightbox.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            lightbox.addEventListener('touchend', function(e) {
                const touchEndX = e.changedTouches[0].screenX;
                const diffX = touchEndX - touchStartX;
                if (Math.abs(diffX) > 40) {
                    if (diffX > 0) {
                        document.dir === 'rtl' ? showNext() : showPrev();
                    } else {
                        document.dir === 'rtl' ? showPrev() : showNext();
                    }
                }
            }, { passive: true });
        }
    });
})();
