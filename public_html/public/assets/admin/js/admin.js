document.addEventListener("DOMContentLoaded", () => {
    const shell = document.querySelector("[data-admin-shell]");
    const sidebarToggle = document.querySelector("[data-admin-sidebar-toggle]");
    const sidebarClose = document.querySelector("[data-admin-sidebar-close]");
    const sidebarOverlay = document.querySelector("[data-admin-sidebar-overlay]");
    const userMenu = document.querySelector("[data-admin-user-menu]");
    const userMenuToggle = document.querySelector("[data-admin-user-menu-toggle]");

    const closeSidebar = () => shell?.classList.remove("is-sidebar-open");
    const closeUserMenu = () => {
        userMenu?.classList.remove("is-open");
        userMenuToggle?.setAttribute("aria-expanded", "false");
    };

    sidebarToggle?.addEventListener("click", () => {
        shell?.classList.add("is-sidebar-open");
        closeUserMenu();
    });

    sidebarClose?.addEventListener("click", closeSidebar);
    sidebarOverlay?.addEventListener("click", closeSidebar);

    userMenuToggle?.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = userMenu?.classList.toggle("is-open") ?? false;
        userMenuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        closeSidebar();
    });

    document.addEventListener("click", (event) => {
        if (userMenu && !userMenu.contains(event.target)) {
            closeUserMenu();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
            closeUserMenu();
        }
    });

    document.querySelectorAll("[data-admin-tabs]").forEach((tabsRoot) => {
        const tabs = Array.from(tabsRoot.querySelectorAll("[data-admin-tab]"));
        const panels = Array.from(document.querySelectorAll("[data-admin-tab-panel]"));

        const activate = (target) => {
            tabs.forEach((tab) => {
                const isActive = tab.getAttribute("data-admin-tab") === target;
                tab.classList.toggle("is-active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            panels.forEach((panel) => {
                const isActive = panel.getAttribute("data-admin-tab-panel") === target;
                panel.classList.toggle("is-active", isActive);
                panel.hidden = !isActive;
            });
        };

        tabs.forEach((tab) => {
            tab.addEventListener("click", (event) => {
                event.preventDefault();
                activate(tab.getAttribute("data-admin-tab"));
            });
        });

        if (tabs.length > 0) {
            activate(tabs.find((tab) => tab.classList.contains("is-active"))?.getAttribute("data-admin-tab") || tabs[0].getAttribute("data-admin-tab"));
        }
    });
});
