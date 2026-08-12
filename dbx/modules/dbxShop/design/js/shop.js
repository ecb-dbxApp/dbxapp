(function (window) {
   "use strict";

   const document = window.document;
   const shop = window.dbxShop = window.dbxShop || {};

   shop.version = "0.2.1";

   shop.syncCartMenu = function (count) {
      count = Math.max(0, parseInt(count, 10) || 0);
      const language = String(document.documentElement.lang || "de").toLowerCase().slice(0, 2);
      const labels = {
         de: {
            empty: "Warenkorb",
            one: "1 Artikel im Warenkorb",
            many: count + " Artikel im Warenkorb"
         },
         en: {
            empty: "Cart",
            one: "1 item in cart",
            many: count + " items in cart"
         },
         es: {
            empty: "Carrito",
            one: "1 artículo en el carrito",
            many: count + " artículos en el carrito"
         }
      };
      const text = labels[language] || labels.de;
      const label = count === 1 ? text.one : text.many;

      document.querySelectorAll(".dbx-shop-cart-menu-link").forEach(link => {
         const icon = link.querySelector(".dbx-shop-cart-menu-icon");
         if (!icon) return;

         let badge = icon.querySelector(".dbx-shop-cart-menu-badge");
         if (count <= 0) {
            if (badge) badge.remove();
            link.setAttribute("data-dbx-tooltip", text.empty);
            return;
         }

         if (!badge) {
            badge = document.createElement("span");
            badge.className = "dbx-shop-cart-menu-badge";
            icon.appendChild(badge);
         }

         badge.textContent = String(count);
         badge.setAttribute("aria-label", label);
         link.setAttribute("data-dbx-tooltip", label);
      });
   };

   shop.syncCartMarker = function (root) {
      if (!root || !root.querySelector) return;

      const marker = root.matches && root.matches("[data-dbx-shop-cart-count]")
         ? root
         : root.querySelector("[data-dbx-shop-cart-count]");

      if (!marker) return;
      shop.syncCartMenu(marker.getAttribute("data-dbx-shop-cart-count"));
   };

   shop.syncCartMarker(document);

   function bindAjaxSync() {
      if (shop.__ajaxSyncBound) return true;
      if (!window.dbx || !window.dbx.event || typeof window.dbx.event.on !== "function") {
         return false;
      }

      shop.__ajaxSyncBound = true;
      window.dbx.event.on("ajax:after", data => {
         shop.syncCartMarker((data && data.targetElement) || document);
      });
      return true;
   }

   if (!bindAjaxSync()) {
      let attempts = 0;
      const wait = window.setInterval(() => {
         if (bindAjaxSync() || ++attempts > 80) {
            window.clearInterval(wait);
         }
      }, 50);
   }
})(window);
