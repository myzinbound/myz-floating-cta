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

    /**
     * チャットボットのトグルボタンと高さ（中央）を揃える
     */
    function alignWithChatbot() {
        var toggle = document.getElementById('myz-chatbot-toggle');
        if (!toggle) return;

        // チャットボットのトグルの位置とサイズを取得
        var toggleRect = toggle.getBoundingClientRect();
        var btnRect = btn.getBoundingClientRect();
        var viewH = window.innerHeight;

        // トグルボタンの中央Y位置（ビューポート下端からの距離）
        var toggleCenterFromBottom = viewH - (toggleRect.top + toggleRect.height / 2);
        // CTAボタンの中央をそこに合わせるために必要なbottom値
        var newBottom = toggleCenterFromBottom - (btnRect.height / 2);

        if (newBottom > 0) {
            btn.style.bottom = newBottom + 'px';
        }
    }

    function showButton() {
        if (!visible) {
            visible = true;
            btn.classList.add('myz-fcta-visible');
            // 表示時にチャットボットと位置を揃える
            requestAnimationFrame(alignWithChatbot);
        }
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
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            visible = false;
            btn.classList.remove('myz-fcta-visible');
        }
    }

    // 初回ロード時にも位置を調整（チャットボットが遅延ロードされる場合に備える）
    window.addEventListener('load', function () {
        setTimeout(alignWithChatbot, 500);
    });

    // 画面リサイズ時にも再調整
    window.addEventListener('resize', function () {
        if (visible) {
            alignWithChatbot();
        }
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();
