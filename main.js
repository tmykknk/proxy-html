"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const params = window.location.search;

  fetch("index.php" + params)
    .then((response) => {
      if (!response.ok) throw new Error("ネットワークエラーが発生しました。");
      return response.text();
    })
    .then((html) => {
      document.getElementById("app").innerHTML = html;

      document.querySelectorAll("#app .reveal").forEach((el) => {
        revealObserver.observe(el);
      });

      if (params.includes("refresh=1")) {
        window.history.replaceState(
          {},
          document.title,
          window.location.pathname,
        );
      }
    })
    .catch((error) => {
      document.getElementById("app").innerHTML =
        `<div class="container pt-5 pb-4"><div class="alert alert-danger">${error.message}</div></div>`;
    });
});

const bannerObserver = new MutationObserver(() => {
  const banner = document.getElementById("vdbanner");
  if (banner && banner.style.display !== "none") {
    banner.style.setProperty("display", "none", "important");
  }
});
bannerObserver.observe(document.documentElement, {
  childList: true,
  subtree: true,
  attributes: true,
  attributeFilter: ["style"],
});

const revealObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("active");
        revealObserver.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.1 },
);
