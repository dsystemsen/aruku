/* aruku — 軽量UXスクリプト（編集誌テーマ） */
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

  // スクロールで要素を順に現す（.reveal / .reveal-stagger → .is-in）
  var targets = document.querySelectorAll(".reveal, .reveal-stagger");
  var reduce =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (!targets.length) return;

  if (reduce || !("IntersectionObserver" in window)) {
    targets.forEach(function (el) {
      el.classList.add("is-in");
    });
    return;
  }

  var io = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-in");
          io.unobserve(entry.target);
        }
      });
    },
    { rootMargin: "0px 0px -10% 0px", threshold: 0.12 }
  );
  targets.forEach(function (el) {
    io.observe(el);
  });
})();
