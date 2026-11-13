</div> 

</main>
</div>
<script>

function updateDateTime(){
    const now = new Date();

    const options={
        weekday:'short',
        year:'numeric',
        month:'short',
        day:'numeric',
        hour:'2-digit',
        minute:'2-digit',
        second:'2-digit',
        hour12:false
    };

    document.getElementById('currentDateTime').textContent =
    now.toLocaleString('en-US',options);
}

updateDateTime();
setInterval(updateDateTime,1000);

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarCollapseToggle = document.getElementById('sidebarCollapseToggle');
    const sidebarRailCollapse = document.getElementById('sidebarRailCollapse');
    const accountMenuToggle = document.getElementById('accountMenuToggle');
    const accountRailMenu = document.getElementById('accountRailMenu');
    const railMenuToggles = document.querySelectorAll('[data-rail-menu-toggle]');
    const railPopoutMenus = document.querySelectorAll('.rail-popout-menu');
    const sidebarSubmenuToggles = document.querySelectorAll('[data-sidebar-submenu]');
    const sidebarLinks = document.querySelectorAll('[data-sidebar-link]');
    const sidebarCollapseStorageKey = 'ims.sidebar.collapsed';
    const sidebarScrollStorageKey = 'ims.sidebar.scrollTop';
    const sidebarPanel = document.querySelector('.sidebar-panel');
    const sidebarMenu = document.querySelector('.sidebar-menu');
    const sidebarModuleSearch = document.getElementById('sidebarModuleSearch');
    const sidebarModuleSearchClear = document.getElementById('sidebarModuleSearchClear');
    const sidebarSearchEmpty = document.getElementById('sidebarSearchEmpty');

function getSidebarScroller() {
    if (sidebarMenu && sidebarMenu.scrollHeight > sidebarMenu.clientHeight) {
        return sidebarMenu;
    }
    if (sidebarPanel && sidebarPanel.scrollHeight > sidebarPanel.clientHeight) {
        return sidebarPanel;
    }
    return sidebarMenu || sidebarPanel;
}

function saveSidebarScroll() {
    const scroller = getSidebarScroller();
    if (!scroller) return;
    sessionStorage.setItem(sidebarScrollStorageKey, String(scroller.scrollTop || 0));
}

function restoreSidebarScroll() {
    const scroller = getSidebarScroller();
    if (!scroller) return;
    const savedScroll = parseInt(sessionStorage.getItem(sidebarScrollStorageKey) || '0', 10);
    if (Number.isNaN(savedScroll)) return;
    scroller.scrollTop = savedScroll;
}

requestAnimationFrame(restoreSidebarScroll);
setTimeout(restoreSidebarScroll, 120);

function setSidebarState(isOpen) {
    document.body.classList.toggle('sidebar-open', Boolean(isOpen));
}

function isDesktopSidebar() {
return window.innerWidth > 991;
}

function setSidebarCollapsed(isCollapsed, shouldStore = true) {
    document.body.classList.toggle('sidebar-collapsed', Boolean(isCollapsed));
        closeRailMenus();
    if (sidebarCollapseToggle) {
        sidebarCollapseToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        sidebarCollapseToggle.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarCollapseToggle.querySelector('i')?.classList.toggle('bi-layout-sidebar-inset', !isCollapsed);
        sidebarCollapseToggle.querySelector('i')?.classList.toggle('bi-layout-sidebar-inset-reverse', Boolean(isCollapsed));
    }
    if (sidebarRailCollapse) {
        sidebarRailCollapse.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        sidebarRailCollapse.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarRailCollapse.querySelector('i')?.classList.toggle('bi-pc-display-horizontal', !isCollapsed);
        sidebarRailCollapse.querySelector('i')?.classList.toggle('bi-layout-sidebar-inset-reverse', Boolean(isCollapsed));
    }
    if (shouldStore) {
        localStorage.setItem(sidebarCollapseStorageKey, isCollapsed ? '1' : '0');
    }
}

function closeRailMenus(exceptMenu = null) {
    railPopoutMenus.forEach(function(menu) {
    if (menu === exceptMenu) return;
    menu.classList.remove('show');
    menu.setAttribute('aria-hidden', 'true');
});

railMenuToggles.forEach(function(toggle) {
const menuId = toggle.getAttribute('data-rail-menu-toggle');
if (exceptMenu && menuId === exceptMenu.id) return;
    toggle.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
});

if (accountRailMenu !== exceptMenu) {
    accountMenuToggle?.classList.remove('open');
    accountMenuToggle?.setAttribute('aria-expanded', 'false');
    }
}

function setRailMenuState(toggle, menu, isOpen) {
if (isOpen) {
closeRailMenus(menu);
if (toggle && menu?.closest('.rail-nav')) {
menu.style.top = `${toggle.offsetTop}px`;
}
} else {
closeRailMenus();
}

    menu?.classList.toggle('show', Boolean(isOpen));
    menu?.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    toggle?.classList.toggle('open', Boolean(isOpen));
    toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function setAccountMenuState(isOpen) {
    setRailMenuState(accountMenuToggle, accountRailMenu, isOpen);
}

if (isDesktopSidebar()) {
    setSidebarCollapsed(localStorage.getItem(sidebarCollapseStorageKey) === '1', false);
}

function setSubmenuState(toggle, submenu, isOpen) {
    if (!toggle) return;
    toggle.classList.toggle('active', Boolean(isOpen));
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    submenu?.classList.toggle('show', Boolean(isOpen));
    toggle.closest('.sidebar-module')?.classList.toggle('open', Boolean(isOpen));
    toggle.closest('.sidebar-nested-group')?.classList.toggle('open', Boolean(isOpen));
}

function normalizeSearchText(value) {
    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function filterSidebarModules(query) {
    const keyword = normalizeSearchText(query);
    const sections = document.querySelectorAll('.sidebar-panel .sidebar-section');
    let visibleLinks = 0;

    sidebarModuleSearch?.closest('.sidebar-module-search')?.classList.toggle('has-value', keyword.length > 0);

    sections.forEach(function(section) {
        const links = section.querySelectorAll('[data-sidebar-link]');
        const toggle = section.querySelector(':scope > [data-sidebar-submenu]');
        const submenuId = toggle?.getAttribute('data-sidebar-submenu');
        const submenu = submenuId ? document.getElementById(submenuId) : null;
        let sectionVisible = false;

        links.forEach(function(link) {
            const text = normalizeSearchText(link.textContent);
            const sectionText = normalizeSearchText(toggle?.textContent || '');
            const matched = keyword === '' || text.includes(keyword) || sectionText.includes(keyword);
            link.classList.toggle('search-hidden', !matched);
            if (matched) {
                sectionVisible = true;
                visibleLinks++;

                if (keyword !== '') {
                    let parentSubmenu = link.closest('.sidebar-submenu');
                    while (parentSubmenu) {
                        const parentToggle = document.querySelector(`[data-sidebar-submenu="${parentSubmenu.id}"]`);
                        setSubmenuState(parentToggle, parentSubmenu, true);
                        parentSubmenu = parentSubmenu.parentElement?.closest('.sidebar-submenu');
                    }
                }
            }
        });

        section.classList.toggle('search-hidden', !sectionVisible);
        if (keyword !== '' && sectionVisible && toggle && submenu) {
            setSubmenuState(toggle, submenu, true);
        }
    });

    sidebarSearchEmpty?.classList.toggle('show', keyword.length > 0 && visibleLinks === 0);
}

function firstVisibleSidebarLink() {
    return Array.from(document.querySelectorAll('.sidebar-panel [data-sidebar-link]'))
        .find(function(link) {
            return !link.classList.contains('search-hidden') && link.offsetParent !== null;
        });
}

sidebarToggle?.addEventListener('click', function() {
if (isDesktopSidebar()) {
setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
return;
}
setSidebarState(!document.body.classList.contains('sidebar-open'));
});

sidebarCollapseToggle?.addEventListener('click', function() {
setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
});

sidebarModuleSearch?.addEventListener('input', function() {
filterSidebarModules(this.value);
});

sidebarModuleSearch?.addEventListener('keydown', function(event) {
if (event.key !== 'Enter') return;
const link = firstVisibleSidebarLink();
if (!link) return;
event.preventDefault();
saveSidebarScroll();
link.click();
});

sidebarModuleSearchClear?.addEventListener('click', function() {
if (!sidebarModuleSearch) return;
sidebarModuleSearch.value = '';
filterSidebarModules('');
sidebarModuleSearch.focus();
});

sidebarRailCollapse?.addEventListener('click', function() {
if (!isDesktopSidebar()) {
setSidebarState(false);
return;
}
setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
});

railMenuToggles.forEach(function(toggle) {
toggle.addEventListener('click', function(event) {
event.stopPropagation();
const menuId = toggle.getAttribute('data-rail-menu-toggle');
const menu = menuId ? document.getElementById(menuId) : null;
if (!menu) return;

if (isDesktopSidebar() && !document.body.classList.contains('sidebar-collapsed')) {
setSidebarCollapsed(true);
setRailMenuState(toggle, menu, true);
return;
}

setRailMenuState(toggle, menu, !menu.classList.contains('show'));
});
});

accountMenuToggle?.addEventListener('click', function(event) {
event.stopPropagation();
if (isDesktopSidebar() && !document.body.classList.contains('sidebar-collapsed')) {
setSidebarCollapsed(true);
setAccountMenuState(true);
return;
}
setAccountMenuState(!accountRailMenu?.classList.contains('show'));
});

document.addEventListener('click', function(event) {
const openMenu = document.querySelector('.rail-popout-menu.show');
if (!openMenu) return;
const clickedToggle = event.target.closest('[data-rail-menu-toggle], #accountMenuToggle');
if (openMenu.contains(event.target) || clickedToggle) return;
closeRailMenus();
});

sidebarOverlay?.addEventListener('click', function() {
setSidebarState(false);
});

sidebarSubmenuToggles.forEach(function(toggle) {
toggle.addEventListener('click', function(event) {
event.preventDefault();
saveSidebarScroll();
const submenuId = toggle.getAttribute('data-sidebar-submenu');
const submenu = submenuId ? document.getElementById(submenuId) : null;
if (!submenu) return;

const shouldOpen = !submenu.classList.contains('show');
setSubmenuState(toggle, submenu, shouldOpen);
restoreSidebarScroll();
});
});

sidebarLinks.forEach(function(link) {
link.addEventListener('click', function(event) {
saveSidebarScroll();
const targetHref = link.getAttribute('href');
if (targetHref) {
    const targetUrl = new URL(targetHref, window.location.origin);
    if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
        event.preventDefault();
        closeRailMenus();
        restoreSidebarScroll();
        return;
    }
}
closeRailMenus();
if (window.innerWidth <= 991) {
setSidebarState(false);
}
});
});

window.addEventListener('resize', function() {
if (window.innerWidth > 991) {
setSidebarState(false);
setSidebarCollapsed(localStorage.getItem(sidebarCollapseStorageKey) === '1', false);
} else {
setSidebarCollapsed(false, false);
}
});

</script>

<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

</body>
</html>
