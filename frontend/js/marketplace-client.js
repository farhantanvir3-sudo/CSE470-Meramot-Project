/* ============================================================================
 * RELOCATED & MERGED CLIENT-SIDE MODULE — Shanid Sajjatuz Islam [23201465]
 * ============================================================================
 * Work Ownership: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Location: frontend/js/marketplace-client.js (Served via /js/marketplace-client.js)
 * Relocated From Inline <script> Blocks In:
 *   1. frontend/views/packages/catalog.blade.php     (Feature R8 - Cart Add)
 *   2. frontend/views/packages/cart.blade.php        (Feature R8 - Qty & Remove)
 *   3. frontend/views/reviews/write-review.blade.php (Feature R9 - Star Rating)
 *   4. frontend/views/ai-consultant/chat.blade.php   (Feature R10 - AI Triage)
 * Improvements Applied (Lead Merge):
 *   - Strict root-level frontend/ vs backend/ separation in Laravel 10 MVC
 *   - Replaced blocking alert() / confirm() dialogs with MeramotToast events
 *   - Unified Shop Service + Quickmatch Package Direct-Order Cart AJAX payloads
 *   - Live synchronization of navbar cart count badges ([data-cart-badge])
 * ============================================================================ */

(function () {
    'use strict';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ------------------------------------------------------------------------
     * Global Non-Blocking Toast Dispatcher (Replaces browser alert() popups)
     * ------------------------------------------------------------------------ */
    const MeramotToast = {
        show(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('meramot-toast', {
                detail: { message, type }
            }));
        }
    };

    function syncCartBadges(count) {
        if (typeof count !== 'number') return;
        document.querySelectorAll('[data-cart-badge]').forEach((el) => {
            el.textContent = count;
            if (count > 0) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    /* ------------------------------------------------------------------------
     * RELOCATED BLOCK 1: Direct-Order & Quickmatch Cart Client Actions (R8)
     * Original Author: Shanid Sajjatuz Islam [23201465]
     * Merged with Unified Direct-Order Shop Service Cart workflow
     * ------------------------------------------------------------------------ */
    const MeramotCart = {
        async addItem(payload, triggerBtn = null) {
            const originalHtml = triggerBtn ? triggerBtn.innerHTML : null;
            if (triggerBtn) {
                triggerBtn.disabled = true;
                triggerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Adding...';
            }

            try {
                const res = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    syncCartBadges(data.cart_count ?? 0);
                    MeramotToast.show(data.message || 'Service added to cart', 'success');
                    return data;
                } else {
                    MeramotToast.show(data.message || 'Unable to add service to cart.', 'error');
                    return null;
                }
            } catch (err) {
                MeramotToast.show('Network error while updating cart.', 'error');
                return null;
            } finally {
                if (triggerBtn && originalHtml !== null) {
                    triggerBtn.disabled = false;
                    triggerBtn.innerHTML = originalHtml;
                }
            }
        },

        async addPackage(packageId, shopSelectId = null, triggerBtn = null) {
            let shopId = null;
            if (shopSelectId) {
                const selectEl = document.getElementById(shopSelectId);
                if (selectEl && selectEl.value) {
                    shopId = parseInt(selectEl.value, 10);
                }
            }
            return this.addItem({
                package_id: packageId,
                shop_id: shopId,
                quantity: 1
            }, triggerBtn);
        },

        async addShopService(shopId, serviceName, description, price, triggerBtn = null) {
            return this.addItem({
                shop_id: shopId,
                service_name: serviceName,
                description: description,
                price: price,
                quantity: 1
            }, triggerBtn);
        },

        async updateQuantity(itemKey, newQuantity) {
            if (newQuantity < 1 || newQuantity > 10) return;

            try {
                const res = await fetch(`/cart/update/${encodeURIComponent(itemKey)}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ quantity: newQuantity })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    MeramotToast.show(data.message || 'Could not update quantity.', 'error');
                }
            } catch (err) {
                MeramotToast.show('Could not update cart item.', 'error');
            }
        },

        async removeItem(itemKey) {
            try {
                const res = await fetch(`/cart/remove/${encodeURIComponent(itemKey)}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });
                const data = await res.json();
                if (data.success) {
                    syncCartBadges(data.cart_count ?? 0);
                    window.location.reload();
                } else {
                    MeramotToast.show(data.message || 'Could not remove item.', 'error');
                }
            } catch (err) {
                MeramotToast.show('Could not remove cart item.', 'error');
            }
        }
    };

    /* ------------------------------------------------------------------------
     * RELOCATED BLOCK 2: Verified Shop Review Star Rating Helper (Feature R9)
     * Original Author: Shanid Sajjatuz Islam [23201465]
     * ------------------------------------------------------------------------ */
    const ratingDescriptions = {
        1: '1 Star — Poor experience, major issues unresolved',
        2: '2 Stars — Below expectations, room for improvement',
        3: '3 Stars — Satisfactory repair, average turnaround',
        4: '4 Stars — Great repair quality & transparent communication',
        5: '5 Stars — Exceptional precision repair & fast courier handling!'
    };

    function setShopReviewRating(rating) {
        const input = document.getElementById('rating-input');
        const label = document.getElementById('rating-text');
        if (input) input.value = rating;
        if (label) label.textContent = ratingDescriptions[rating] || 'Click stars to rate';

        document.querySelectorAll('#star-rating [data-rating]').forEach((starBtn) => {
            const val = parseInt(starBtn.getAttribute('data-rating'), 10);
            if (val <= rating) {
                starBtn.classList.add('text-amber-500', 'scale-110');
                starBtn.classList.remove('text-[var(--muted)]', 'opacity-50');
            } else {
                starBtn.classList.remove('text-amber-500', 'scale-110');
                starBtn.classList.add('text-[var(--muted)]', 'opacity-50');
            }
        });
    }

    window.MeramotToast = MeramotToast;
    window.MeramotCart = MeramotCart;
    window.addToCart = (packageId, btn) => MeramotCart.addPackage(packageId, `shop-select-${packageId}`, btn);
    window.updateQuantity = (itemKey, qty) => MeramotCart.updateQuantity(itemKey, qty);
    window.removeFromCart = (itemKey) => MeramotCart.removeItem(itemKey);
    window.setRating = setShopReviewRating;
})();
