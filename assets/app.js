/* aruku — 軽量UXスクリプト（編集誌テーマ） */
(function () {
  "use strict";

  // いいね／保存：ページ遷移せずその場でトグル（最上部スクロール防止）。
  // 後続コードで万一エラーが出ても確実に登録されるよう最初に設置。
  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || !form.classList || !form.classList.contains("like-form")) return;
    e.preventDefault();
    try {
      fetch(form.getAttribute("action"), { method: "POST", body: new FormData(form), credentials: "same-origin" }).catch(function () {});
    } catch (err) {}
    var btn = form.querySelector("button");
    if (!btn) return;
    if (btn.classList.contains("like-btn")) {
      var span = btn.querySelector("span");
      var n = span ? parseInt(span.textContent, 10) || 0 : 0;
      if (btn.classList.toggle("liked")) { if (span) span.textContent = n + 1; }
      else if (span) span.textContent = Math.max(0, n - 1);
    } else if (btn.classList.contains("bm-btn")) {
      btn.innerHTML = btn.classList.toggle("marked") ? "🔖 保存済み" : "🔖 保存";
    }
  });

  // スクロールでナビに影を付ける
  var nav = document.querySelector(".lp-nav");
  if (nav) {
    var onScroll = function () {
      nav.style.boxShadow = window.scrollY > 8 ? "var(--shadow-sm)" : "none";
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  // トースト通知
  function showToast(msg) {
    var t = document.createElement("div");
    t.className = "aruku-toast";
    t.setAttribute("role", "status");
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add("is-show"); });
    setTimeout(function () {
      t.classList.remove("is-show");
      setTimeout(function () { t.remove(); }, 320);
    }, 2200);
  }

  // ボタンの真下に吹き出しを表示
  var curBubble = null;
  function showBubble(anchor, msg) {
    if (curBubble) { curBubble.remove(); curBubble = null; }
    var b = document.createElement("div");
    b.className = "aruku-bubble";
    b.setAttribute("role", "status");
    b.textContent = msg;
    document.body.appendChild(b);
    var r = anchor.getBoundingClientRect();
    b.style.top = (r.bottom + 10) + "px";
    b.style.left = (r.left + r.width / 2) + "px";
    requestAnimationFrame(function () { b.classList.add("is-show"); });
    curBubble = b;
    setTimeout(function () {
      b.classList.remove("is-show");
      setTimeout(function () { if (b === curBubble) curBubble = null; b.remove(); }, 280);
    }, 2000);
  }

  // ログイン中：マイページ/会員登録/ログインを押したときに吹き出しで通知（遷移しても無反応に見えるため）
  if (nav && nav.getAttribute("data-auth") === "in") {
    var onMypage = /\/member\/mypage\.php/.test(location.pathname);
    nav.querySelectorAll(".lp-nav-links a").forEach(function (a) {
      var href = a.getAttribute("href") || "";
      if (/member\/(register|login)\.php/.test(href)) {
        a.addEventListener("click", function (e) { e.preventDefault(); showBubble(a, "すでにログインしています"); });
      } else if (/member\/mypage\.php/.test(href) && onMypage) {
        a.addEventListener("click", function (e) { e.preventDefault(); showBubble(a, "マイページを表示中です"); });
      }
    });
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

  // カテゴリレールの左右矢印ボタン
  document.querySelectorAll(".cat-rail-scroller").forEach(function (sc) {
    var rail = sc.querySelector(".note-rail");
    if (!rail) return;
    var prev = sc.querySelector(".rail-arrow.prev");
    var next = sc.querySelector(".rail-arrow.next");
    var step = function (dir) {
      rail.scrollBy({ left: dir * Math.max(240, rail.clientWidth * 0.85), behavior: "smooth" });
    };
    if (prev) prev.addEventListener("click", function () { step(-1); });
    if (next) next.addEventListener("click", function () { step(1); });
    var update = function () {
      if (prev) prev.classList.toggle("is-hidden", rail.scrollLeft <= 2);
      if (next) next.classList.toggle("is-hidden", rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2);
    };
    rail.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update, { passive: true });
    update();
  });

  // ===== コラムエディタ：Markdownツールバー＋プレビュー =====
  document.querySelectorAll(".md-toolbar").forEach(function (bar) {
    var ta = document.getElementById(bar.getAttribute("data-md-target"));
    if (!ta) return;
    var field = bar.closest(".md-field");
    var preview = field ? field.querySelector(".md-preview") : null;

    // 選択範囲を取得して置換するヘルパ
    var surround = function (before, after) {
      after = after || "";
      var s = ta.selectionStart, e = ta.selectionEnd;
      var sel = ta.value.slice(s, e);
      var ins = before + sel + after;
      ta.setRangeText(ins, s, e, "end");
      if (!sel) ta.setSelectionRange(s + before.length, s + before.length);
      ta.focus();
    };
    // 行頭にプレフィックスを付ける（見出し・リスト・引用）
    var linePrefix = function (prefix) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      var start = ta.value.lastIndexOf("\n", s - 1) + 1;
      var seg = ta.value.slice(start, e);
      var out = seg.split("\n").map(function (ln, i) {
        var p = prefix;
        if (prefix === "1. ") p = (i + 1) + ". ";
        return ln.replace(/^(\s*(?:#{2,3}\s|[-*]\s|\d+\.\s|>\s))?/, "") ? p + ln.replace(/^(\s*(?:#{2,3}\s|[-*]\s|\d+\.\s|>\s))?/, "") : p + ln;
      }).join("\n");
      ta.setRangeText(out, start, e, "end");
      ta.focus();
    };

    var editor = bar.closest(".post-editor");
    var fileInput = editor ? editor.querySelector("#md-image-input") : null;
    var dropMsg = field ? field.querySelector(".md-dropmsg") : null;

    var surroundRaw = function (text) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      ta.setRangeText(text, s, e, "end");
      ta.focus();
      fireInput();
    };
    var fireInput = function () { ta.dispatchEvent(new Event("input", { bubbles: true })); };

    var actions = {
      h2: function () { linePrefix("## "); fireInput(); },
      h3: function () { linePrefix("### "); fireInput(); },
      bold: function () { surround("**", "**"); fireInput(); },
      italic: function () { surround("*", "*"); fireInput(); },
      ul: function () { linePrefix("- "); fireInput(); },
      ol: function () { linePrefix("1. "); fireInput(); },
      quote: function () { linePrefix("> "); fireInput(); },
      link: function () {
        var url = window.prompt("リンク先URL（https://…）", "https://");
        if (!url) return;
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || "リンク";
        surroundRaw("[" + sel + "](" + url + ")");
      },
      image: function () { if (fileInput) fileInput.click(); },
      preview: function (btn) { togglePreview(btn); },
      fullscreen: function (btn) {
        if (!editor) return;
        var on = editor.classList.toggle("is-fullscreen");
        document.body.classList.toggle("md-fs-lock", on);
        btn.setAttribute("aria-pressed", on ? "true" : "false");
      }
    };

    var togglePreview = function (btn) {
      if (!preview) return;
      var on = preview.hasAttribute("hidden");
      if (on) {
        preview.innerHTML = mdLite(ta.value);
        preview.removeAttribute("hidden");
        preview.setAttribute("aria-hidden", "false");
        ta.parentNode.style.display = "none";
      } else {
        preview.setAttribute("hidden", "");
        preview.setAttribute("aria-hidden", "true");
        ta.parentNode.style.display = "";
      }
      btn.setAttribute("aria-pressed", on ? "true" : "false");
      btn.textContent = on ? "編集に戻る" : "プレビュー";
    };

    bar.querySelectorAll(".md-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var fn = actions[btn.getAttribute("data-md")];
        if (fn) fn(btn);
      });
    });

    // ---- 画像アップロード（ボタン / D&D / 貼り付け）----
    var uploadUrl = editor ? editor.getAttribute("data-upload") : null;
    var csrf = editor ? editor.getAttribute("data-csrf") : null;

    var insertAtCursor = function (text) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      ta.setRangeText(text, s, e, "end");
      fireInput();
    };
    var uploadImage = function (fileObj) {
      if (!uploadUrl || !fileObj || !/^image\//.test(fileObj.type)) return;
      var token = "\n![アップロード中…](uploading)\n";
      insertAtCursor(token);
      var fd = new FormData();
      fd.append("file", fileObj);
      fd.append("csrf", csrf || "");
      fetch(uploadUrl, { method: "POST", headers: { "X-CSRF": csrf || "" }, body: fd, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.ok) {
            ta.value = ta.value.replace(token.trim(), "![](" + j.url + ")");
          } else {
            ta.value = ta.value.replace(token.trim(), "");
            alert((j && j.error) || "画像のアップロードに失敗しました。");
          }
          fireInput();
        })
        .catch(function () {
          ta.value = ta.value.replace(token.trim(), "");
          fireInput();
          alert("画像のアップロードに失敗しました。通信環境をご確認ください。");
        });
    };
    var uploadFiles = function (list) {
      Array.prototype.slice.call(list || []).forEach(function (f) {
        if (/^image\//.test(f.type)) uploadImage(f);
      });
    };

    if (fileInput) {
      fileInput.addEventListener("change", function () { uploadFiles(this.files); this.value = ""; });
    }
    // ドラッグ&ドロップ
    var zone = field ? field.querySelector(".md-editzone") : null;
    if (zone) {
      ["dragenter", "dragover"].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add("is-dragover"); });
      });
      ["dragleave", "dragend"].forEach(function (ev) {
        zone.addEventListener(ev, function () { zone.classList.remove("is-dragover"); });
      });
      zone.addEventListener("drop", function (e) {
        e.preventDefault();
        zone.classList.remove("is-dragover");
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
      });
    }
    // クリップボード貼り付け
    ta.addEventListener("paste", function (e) {
      var items = e.clipboardData && e.clipboardData.items;
      if (!items) return;
      for (var i = 0; i < items.length; i++) {
        if (items[i].type && items[i].type.indexOf("image") === 0) {
          var f = items[i].getAsFile();
          if (f) { e.preventDefault(); uploadImage(f); }
        }
      }
    });

    // ---- 文字数カウンター＋読了時間 ----
    var countEl = field ? field.querySelector(".md-count") : null;
    var updateCount = function () {
      if (!countEl) return;
      var n = Array.from(ta.value).length;
      var min = Math.max(1, Math.round(n / 500)); // 約500字/分
      countEl.textContent = "本文 " + n.toLocaleString() + " 文字 ・ 約" + min + "分で読めます";
    };

    // 文字数カウンターを入力時に更新（自動保存・復元機能は廃止）
    ta.addEventListener("input", updateCount);
    var form = ta.closest("form");

    // ---- キーボードショートカット ----
    ta.addEventListener("keydown", function (e) {
      var mod = e.ctrlKey || e.metaKey;
      if (!mod) return;
      var k = e.key.toLowerCase();
      if (k === "b") { e.preventDefault(); actions.bold(); }
      else if (k === "i") { e.preventDefault(); actions.italic(); }
      else if (k === "s") {
        e.preventDefault();
        var draftBtn = form ? form.querySelector('button[name="save_draft"]') : null;
        if (draftBtn) draftBtn.click();
      } else if (k === "enter") {
        e.preventDefault();
        var pubBtn = form ? form.querySelector('button[name="submit_post"]') : null;
        if (pubBtn) pubBtn.click();
      }
    });
    // 全画面を Esc で解除
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && editor && editor.classList.contains("is-fullscreen")) {
        editor.classList.remove("is-fullscreen");
        document.body.classList.remove("md-fs-lock");
        var fb = bar.querySelector(".md-fullscreen");
        if (fb) fb.setAttribute("aria-pressed", "false");
      }
    });

    updateCount();
  });

  // サムネイル：ドラッグ&ドロップ／クリックで1枚選択 → ボックス内にプレビュー＋サイズ調整
  document.querySelectorAll(".file-drop").forEach(function (zone) {
    var input = zone.querySelector('input[type="file"]');
    if (!input) return;
    var empty = zone.querySelector(".file-drop-empty");
    var filled = zone.querySelector(".file-drop-filled");
    var img = zone.querySelector(".file-drop-preview");
    var range = zone.querySelector(".file-size-range");
    var clearBtn = zone.querySelector(".file-clear");

    var applySize = function () { if (img && range) img.style.width = range.value + "px"; };
    var show = function (file) {
      var rd = new FileReader();
      rd.onload = function () { if (img) img.src = rd.result; };
      rd.readAsDataURL(file);
      if (empty) empty.setAttribute("hidden", "");
      if (filled) filled.removeAttribute("hidden");
      applySize();
    };
    var reset = function () {
      input.value = "";
      if (img) img.removeAttribute("src");
      if (filled) filled.setAttribute("hidden", "");
      if (empty) empty.removeAttribute("hidden");
    };
    var setFile = function (file) {
      if (!file || !/^image\//.test(file.type)) return;
      try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch (e) {}
      show(file);
    };

    zone.addEventListener("click", function (e) {
      if (e.target.closest(".file-size-ctrl")) return; // スライダー・削除はクリック対象外
      input.click();
    });
    ["dragenter", "dragover"].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add("is-dragover"); });
    });
    ["dragleave", "dragend"].forEach(function (ev) {
      zone.addEventListener(ev, function () { zone.classList.remove("is-dragover"); });
    });
    zone.addEventListener("drop", function (e) {
      e.preventDefault();
      zone.classList.remove("is-dragover");
      var files = e.dataTransfer && e.dataTransfer.files;
      if (files && files.length) setFile(files[0]); // 1枚のみ
    });
    input.addEventListener("change", function () {
      if (input.files && input.files.length) {
        if (input.files.length > 1) { try { var dt = new DataTransfer(); dt.items.add(input.files[0]); input.files = dt.files; } catch (e) {} }
        show(input.files[0]);
      } else { reset(); }
    });
    if (range) range.addEventListener("input", applySize);
    if (clearBtn) clearBtn.addEventListener("click", function (e) { e.stopPropagation(); reset(); });
  });

  // 軽量Markdown→HTML（プレビュー用。サーバ側 aruku_markdown のサブセットを反映）
  function mdLite(md) {
    var esc = function (s) {
      return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    };
    var inline = function (s) {
      // 画像（リンクより先に）。相対 uploads/ はエディタ基準(../)で解決
      s = s.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, function (whole, alt, url) {
        if (!/^(https?:\/\/|(\.\.\/)?uploads\/)/.test(url)) return whole;
        if (/^uploads\//.test(url)) url = "../" + url;
        return '<img src="' + url + '" alt="' + alt + '" class="post-inline-img">';
      });
      s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>');
      s = s.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
      s = s.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, "$1<em>$2</em>");
      s = s.replace(/`([^`]+)`/g, "<code>$1</code>");
      return s;
    };
    var lines = esc(md.replace(/\r\n/g, "\n")).split("\n");
    var html = "", para = [], inUl = false, inOl = false;
    var flush = function () { if (para.length) { html += "<p>" + para.join("<br>\n") + "</p>"; para = []; } };
    var closeL = function () { if (inUl) { html += "</ul>"; inUl = false; } if (inOl) { html += "</ol>"; inOl = false; } };
    lines.forEach(function (raw) {
      var t = raw.replace(/\s+$/, ""), m;
      if (t.trim() === "") { flush(); closeL(); return; }
      if ((m = t.match(/^###\s+(.+)$/))) { flush(); closeL(); html += "<h3>" + inline(m[1]) + "</h3>"; return; }
      if ((m = t.match(/^##\s+(.+)$/)))  { flush(); closeL(); html += "<h2>" + inline(m[1]) + "</h2>"; return; }
      if ((m = t.match(/^&gt;\s?(.*)$/))) { flush(); closeL(); html += "<blockquote>" + inline(m[1]) + "</blockquote>"; return; }
      if ((m = t.match(/^(?:-|\*)\s+(.+)$/))) { flush(); if (!inUl) { closeL(); html += "<ul>"; inUl = true; } html += "<li>" + inline(m[1]) + "</li>"; return; }
      if ((m = t.match(/^\d+\.\s+(.+)$/))) { flush(); if (!inOl) { closeL(); html += "<ol>"; inOl = true; } html += "<li>" + inline(m[1]) + "</li>"; return; }
      closeL(); para.push(inline(t));
    });
    flush(); closeL();
    return html || "<p class='md-empty'>（プレビューする内容がありません）</p>";
  }

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
