/* aruku CMS — 管理画面スクリプト */
(function () {
  "use strict";

  var rowSeq = 100000; // 追加行のユニークindex（サーバ側で再採番）

  // ---- 記事一覧の絞り込み ----
  var filter = document.getElementById("filter");
  if (filter) {
    filter.addEventListener("input", function () {
      var q = filter.value.trim().toLowerCase();
      document.querySelectorAll("#list-root tr").forEach(function (tr) {
        if (!tr.querySelector(".c-title")) return;
        var t = tr.textContent.toLowerCase();
        tr.style.display = q === "" || t.indexOf(q) !== -1 ? "" : "none";
      });
    });
  }

  // ---- 広告枠トグル ----
  var affToggle = document.getElementById("aff_enabled");
  var affFields = document.getElementById("aff-fields");
  if (affToggle && affFields) {
    var syncAff = function () { affFields.style.display = affToggle.checked ? "" : "none"; };
    affToggle.addEventListener("change", syncAff);
    syncAff();
  }

  // ---- 行の追加 ----
  document.querySelectorAll("[data-add]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var kind = btn.getAttribute("data-add");
      var tplId = kind === "faq" ? "tpl-faq"
        : kind === "page-section" ? "tpl-page-section"
        : "tpl-section";
      var container = kind === "faq"
        ? document.getElementById("faq")
        : document.getElementById("sections");
      var tpl = document.getElementById(tplId);
      if (!tpl || !container) return;
      var html = tpl.innerHTML.replace(/__I__/g, String(rowSeq++));
      var wrap = document.createElement("div");
      wrap.innerHTML = html.trim();
      var node = wrap.firstElementChild;
      container.appendChild(node);
      enhanceTextareas(node);
      var firstInput = node.querySelector("input, textarea");
      if (firstInput) firstInput.focus();
    });
  });

  // ---- 行の削除（イベント委譲） ----
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-remove-row]");
    if (!btn) return;
    var row = btn.closest("[data-row]");
    if (!row) return;
    var container = row.parentElement;
    var rows = container.querySelectorAll("[data-row]");
    if (rows.length <= 1) {
      // 最後の1行は中身だけクリア
      row.querySelectorAll("input, textarea").forEach(function (el) { el.value = ""; });
    } else {
      row.remove();
    }
  });

  // ---- 簡易ツールバー（richtext textarea） ----
  var TOOLS = [
    { label: "見出し", title: "小見出し h3", ins: ["<h3>", "見出し", "</h3>\n"] },
    { label: "太字", title: "強調", ins: ["<strong>", "強調", "</strong>"] },
    { label: "段落", title: "段落", ins: ["<p>", "本文", "</p>\n"] },
    { label: "リスト", title: "箇条書き", ins: ["<ul>\n  <li>", "項目", "</li>\n  <li>項目</li>\n</ul>\n"] },
    { label: "番号", title: "番号リスト", ins: ["<ol>\n  <li>", "項目", "</li>\n  <li>項目</li>\n</ol>\n"] },
    { label: "リンク", title: "リンク", ins: ['<a href="https://">', "リンク文字", "</a>"] },
    { label: "表", title: "表", ins: ['<div class="column-table-wrap"><table class="column-table"><thead><tr><th>項目</th><th>値</th></tr></thead><tbody><tr><td>', "A", "</td><td>B</td></tr></tbody></table></div>\n"] },
    { label: "💡ポイント", title: "コールアウト", ins: ['<p class="column-callout">💡 ', "ここに結論・ポイント", "</p>\n"] },
    { label: "※注意", title: "注意書き", ins: ['<p class="column-note">※ ', "注意書き", "</p>\n"] }
  ];

  function insertAround(ta, before, placeholder, after) {
    var start = ta.selectionStart, end = ta.selectionEnd;
    var sel = ta.value.substring(start, end) || placeholder;
    var text = before + sel + after;
    ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
    var caret = start + before.length;
    ta.focus();
    ta.setSelectionRange(caret, caret + sel.length);
    ta.dispatchEvent(new Event("input", { bubbles: true }));
  }

  function buildToolbar(ta) {
    var bar = document.createElement("div");
    bar.className = "toolbar";
    TOOLS.forEach(function (t) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "tool";
      b.textContent = t.label;
      b.title = t.title;
      b.addEventListener("click", function () {
        insertAround(ta, t.ins[0], t.ins[1], t.ins[2]);
      });
      bar.appendChild(b);
    });
    return bar;
  }

  function enhanceTextareas(root) {
    (root || document).querySelectorAll("textarea.richtext").forEach(function (ta) {
      if (ta.dataset.enhanced) return;
      ta.dataset.enhanced = "1";
      var bar = buildToolbar(ta);
      ta.parentNode.insertBefore(bar, ta);
    });
  }

  enhanceTextareas(document);

  // ---- スラッグ自動生成（新規記事でタイトル→空スラッグのとき補助） ----
  // 日本語タイトルは自動変換できないため、英数字入力時のみ整形
  var slugInput = document.querySelector('input[name="slug"]');
  if (slugInput) {
    slugInput.addEventListener("input", function () {
      slugInput.value = slugInput.value.toLowerCase().replace(/[^a-z0-9\-]/g, "");
    });
  }
})();
