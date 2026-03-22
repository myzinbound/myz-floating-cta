/* MYZ Floating CTA Button - Scroll Show/Hide */
(function () {
    'use strict';

    var btn = document.getElementById('myz-floating-cta');
    if (!btn) return;

    var offset = (typeof myzFctaConfig !== 'undefined' && myzFctaConfig.scrollOffset)
        ? parseInt(myzFctaConfig.scrollOffset, 10)
        : 200;

    var hideDelay = (typeof myzFctaConfig !== 'undefined' && myzFctaConfig.hideDelay)
        ? parseInt(myzFctaConfig.hideDelay, 10)
        : 3000;

    var visible = false;
    var hideTimer = null;

    function showButton() {
        if (!visible) {
            visible = true;
            btn.classList.add('myz-fcta-visible');
        }
        // スクロール中はタイマーをリセット
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
    }

    function scheduleHide() {
        if (hideTimer) {
            clearTimeout(hideTimer);
        }
        hideTimer = setTimeout(function () {
            visible = false;
            btn.classList.remove('myz-fcta-visible');
            hideTimer = null;
        }, hideDelay);
    }

    function onScroll() {
        var scrollY = window.pageYOffset || document.documentElement.scrollTop;
        if (scrollY >= offset) {
            showButton();
            scheduleHide();
        } else if (scrollY < offset && visible) {
            // スクロール位置が上に戻ったら即非表示
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            visible = false;
            btn.classList.remove('myz-fcta-visible');
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();
