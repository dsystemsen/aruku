/* aruku - 軽量UXスクリプト */
(function () {
  "use strict";

  // スクロールでナビに影を付ける
  var nav = document.querySelector(".lp-nav");
  if (nav) {
    var onScroll = function () {
      nav.style.boxShadow = window.scrollY > 8 ? "var(--shadow-sm)" : "none";
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  // 目次クリックでスムーズスクロール（scroll-margin-top はCSS側で確保）
  document.querySelectorAll('.column-toc a[href^="#"]').forEach(function (a) {
    a.addEventListener("click", function (e) {
      var t = document.querySelector(a.getAttribute("href"));
      if (t) {
        e.preventDefault();
        t.scrollIntoView({ behavior: "smooth", block: "start" });
        history.replaceState(null, "", a.getAttribute("href"));
      }
    });
  });
})();
