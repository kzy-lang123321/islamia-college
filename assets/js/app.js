/**
 * Islamia College of Technology - College Management System Application Shell
 * Global Search • Accessible Modals • Toast Notifications • UX Controls
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    initCollapsibleNav();
    initGlobalSearch();
    initUserDropdown();
    initTabs();
    initPasswordToggles();
    initFormDebounce();
    initBackspaceNavigation();
});

/**
 * Mobile Sidebar Drawer Navigation
 */
function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const closeBtn = document.getElementById('sidebar-close-btn');
    const overlay = document.getElementById('sidebar-overlay');
    const collapseDesktopBtn = document.getElementById('sidebar-collapse-desktop-btn');

    // Restore desktop collapsed state on load
    if (window.innerWidth > 1024) {
        const isCollapsed = localStorage.getItem('ict_sidebar_collapsed') === '1';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
            document.documentElement.classList.add('sidebar-collapsed');
        } else {
            document.body.classList.remove('sidebar-collapsed');
            document.documentElement.classList.remove('sidebar-collapsed');
        }
    }

    const toggleDesktopCollapse = () => {
        const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
        localStorage.setItem('ict_sidebar_collapsed', isCollapsed ? '1' : '0');
    };

    const openMobileSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    };

    const closeMobileSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    };

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.innerWidth <= 1024) {
                if (sidebar && sidebar.classList.contains('open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            } else {
                toggleDesktopCollapse();
            }
        });
    }

    if (collapseDesktopBtn) {
        collapseDesktopBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDesktopCollapse();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeMobileSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', (e) => {
            e.stopPropagation();
            closeMobileSidebar();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (sidebar && sidebar.classList.contains('open')) {
                closeMobileSidebar();
            }
        }
    });

    // Close mobile drawer when clicking navigation links on mobile/tablet screens
    if (sidebar) {
        sidebar.querySelectorAll('.nav-sublink, .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    closeMobileSidebar();
                }
            });
        });
    }

    // Auto-close if resized to desktop view
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            if (sidebar && sidebar.classList.contains('open')) {
                closeMobileSidebar();
            }
            const isCollapsed = localStorage.getItem('ict_sidebar_collapsed') === '1';
            document.body.classList.toggle('sidebar-collapsed', isCollapsed);
            document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
        } else {
            document.body.classList.remove('sidebar-collapsed');
            document.documentElement.classList.remove('sidebar-collapsed');
        }
    });
}

/**
 * Collapsible Accordion Navigation for Sidebar
 */
function initCollapsibleNav() {
    const sections = document.querySelectorAll('.nav-section');
    if (!sections.length) return;

    sections.forEach(section => {
        const sectionId = section.dataset.section;
        const headerBtn = section.querySelector('.nav-section-header');
        const categoryLabel = section.previousElementSibling && section.previousElementSibling.classList.contains('nav-category')
            ? section.previousElementSibling
            : null;

        // Check if any link inside this section is currently active
        const hasActiveChild = !!section.querySelector('.nav-sublink.active, .nav-link.active');

        if (hasActiveChild) {
            section.classList.add('expanded', 'has-active');
            if (headerBtn) headerBtn.setAttribute('aria-expanded', 'true');
        } else if (sectionId) {
            // Restore from localStorage if explicitly set by user
            const savedState = localStorage.getItem('ict_nav_' + sectionId);
            if (savedState === 'open') {
                section.classList.add('expanded');
                if (headerBtn) headerBtn.setAttribute('aria-expanded', 'true');
            } else if (savedState === 'closed') {
                section.classList.remove('expanded');
                if (headerBtn) headerBtn.setAttribute('aria-expanded', 'false');
            }
        }

        const toggleSection = (e) => {
            if (e) e.preventDefault();
            const isExpanded = section.classList.toggle('expanded');
            if (headerBtn) {
                headerBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            }
            if (sectionId) {
                localStorage.setItem('ict_nav_' + sectionId, isExpanded ? 'open' : 'closed');
            }
        };

        if (headerBtn) {
            headerBtn.addEventListener('click', toggleSection);
        }

        if (categoryLabel) {
            categoryLabel.style.cursor = 'pointer';
            categoryLabel.title = 'Click to toggle section';
            categoryLabel.addEventListener('click', toggleSection);
        }
    });
}

/**
 * Global Search with Autocomplete & Keyboard Navigation
 */
function initGlobalSearch() {
    const searchInput = document.getElementById('global-search-input');
    const resultsContainer = document.getElementById('global-search-results');

    if (!searchInput || !resultsContainer) return;

    let debounceTimer = null;
    let activeIndex = -1;

    searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        const query = e.target.value.trim();

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            resultsContainer.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            const baseUri = window.location.pathname.includes('/islamia%20college') ? '/islamia%20college' : '';
            fetch(`${baseUri}/api/search/index.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    renderSearchResults(data, resultsContainer);
                })
                .catch(() => {
                    resultsContainer.style.display = 'none';
                });
        }, 250);
    });

    searchInput.addEventListener('keydown', (e) => {
        const items = resultsContainer.querySelectorAll('.search-result-item');
        if (!items.length || resultsContainer.style.display === 'none') return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            highlightItem(items, activeIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            highlightItem(items, activeIndex);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            items[activeIndex].click();
        } else if (e.key === 'Escape') {
            resultsContainer.style.display = 'none';
        }
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

function highlightItem(items, index) {
    items.forEach((item, i) => {
        if (i === index) {
            item.style.background = 'rgba(56, 189, 248, 0.15)';
            item.focus();
        } else {
            item.style.background = 'transparent';
        }
    });
}

function renderSearchResults(data, container) {
    if (!data || !data.results || !data.results.length) {
        container.innerHTML = `
            <div class="search-dropdown-list" style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                No matches found across students, staff, or courses.
            </div>
        `;
        container.style.display = 'block';
        return;
    }

    const html = data.results.map(item => `
        <a href="${item.url}" class="search-result-item">
            <span style="font-size: 18px;">${item.icon || '🔍'}</span>
            <div style="flex:1;">
                <div class="search-item-title">${escapeHtml(item.title)}</div>
                <div class="search-item-sub">${escapeHtml(item.subtitle)} • <span style="color: var(--primary-light);">${escapeHtml(item.category)}</span></div>
            </div>
        </a>
    `).join('');

    container.innerHTML = `<div class="search-dropdown-list">${html}</div>`;
    container.style.display = 'block';
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * User Profile Header Dropdown Menu
 */
function initUserDropdown() {
    const containers = document.querySelectorAll('.user-profile-container');
    containers.forEach(container => {
        const toggle = container.querySelector('.user-profile-pill');
        if (!toggle) return;

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            container.classList.toggle('open');
        });
    });

    document.addEventListener('click', (e) => {
        containers.forEach(container => {
            if (!container.contains(e.target)) {
                container.classList.remove('open');
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            containers.forEach(container => container.classList.remove('open'));
        }
    });
}

/**
 * Tab Switcher Controls
 */
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');
            const parent = btn.closest('.tab-wrapper') || document;

            parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            parent.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(targetId);
            if (targetPane) targetPane.classList.add('active');
        });
    });
}

/**
 * Safe In-Memory Password Visibility Toggles
 */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const group = btn.closest('.input-group') || btn.parentElement;
            const input = group.querySelector('input[type="password"], input[type="text"]');
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = '🙈';
                } else {
                    input.type = 'password';
                    btn.textContent = '👁️';
                }
            }
        });
    });
}

/**
 * Prevent Duplicate Submissions on Forms
 */
function initFormDebounce() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.hasAttribute('data-no-debounce')) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = `<span>Saving...</span> <span>⏳</span>`;
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 8000);
            }
        });
    });
}

/**
 * Toast Notification System
 */
window.showToast = function(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '⚠️';
    if (type === 'warning') icon = '🔔';

    toast.innerHTML = `
        <span style="font-size: 18px;">${icon}</span>
        <div style="flex:1;">${escapeHtml(message)}</div>
        <button type="button" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(16px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4500);
};

/**
 * Modal Dialog Open and Close Helpers
 */
window.openModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
};

window.closeModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
};

/**
 * Delete Confirmation Dialog
 */
window.confirmAction = function(title, message, onConfirm) {
    let confirmModal = document.getElementById('global-confirm-modal');
    if (!confirmModal) {
        confirmModal = document.createElement('div');
        confirmModal.id = 'global-confirm-modal';
        confirmModal.className = 'modal-overlay';
        confirmModal.innerHTML = `
            <div class="modal-dialog" style="max-width: 440px;">
                <div class="modal-header">
                    <h3 class="modal-title" id="confirm-modal-title">Confirm Action</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('global-confirm-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="confirm-modal-message" style="color: var(--text-secondary); font-size: 14px; line-height: 1.5;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-glass" onclick="closeModal('global-confirm-modal')">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-modal-btn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(confirmModal);
    }

    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;

    const confirmBtn = document.getElementById('confirm-modal-btn');
    confirmBtn.onclick = () => {
        closeModal('global-confirm-modal');
        if (typeof onConfirm === 'function') onConfirm();
    };

    openModal('global-confirm-modal');
};

/**
 * Backspace Key Navigation
 * Enables navigating back to the previous page using the Backspace key,
 * while safely preserving standard Backspace deletion when actively typing
 * inside text inputs, textareas, or contenteditable fields.
 */
function initBackspaceNavigation() {
    window.addEventListener('keydown', (e) => {
        // Only process Backspace key
        if (e.key !== 'Backspace' && e.keyCode !== 8) {
            return;
        }

        // Allow modifier key combinations (e.g., Ctrl+Backspace) without navigation
        if (e.altKey || e.ctrlKey || e.metaKey) {
            return;
        }

        // Determine currently focused element or event target
        const active = document.activeElement;
        const target = e.target || active;
        const elem = target || active;

        if (elem) {
            // If focused in rich contenteditable area, let Backspace delete text
            if (elem.isContentEditable || (elem.closest && elem.closest('[contenteditable="true"]'))) {
                return;
            }

            const tag = (elem.tagName || '').toUpperCase();

            // If focused in a multi-line textarea, let Backspace delete text
            if (tag === 'TEXTAREA') {
                if (!elem.readOnly && !elem.disabled) {
                    return;
                }
            }

            // If focused in editable input fields, let Backspace delete text
            if (tag === 'INPUT') {
                const type = (elem.type || 'text').toLowerCase();
                const textInputTypes = [
                    'text', 'password', 'email', 'number', 'search',
                    'tel', 'url', 'date', 'datetime-local', 'month', 'time', 'week'
                ];
                if (textInputTypes.includes(type) && !elem.readOnly && !elem.disabled) {
                    return;
                }
            }

            // If inside select dropdown, do not navigate back
            if (tag === 'SELECT' && !elem.disabled) {
                return;
            }
        }

        // If an open modal dialog is active, dismiss the modal first (safe cancel)
        const activeModal = document.querySelector('.modal-overlay.active');
        if (activeModal) {
            e.preventDefault();
            if (typeof window.closeModal === 'function' && activeModal.id) {
                window.closeModal(activeModal.id);
            } else {
                activeModal.classList.remove('active');
                document.body.style.overflow = '';
            }
            return;
        }

        // Prevent default browser behavior and navigate back
        e.preventDefault();

        if (window.history && window.history.length > 1) {
            window.history.back();
        } else {
            // Fallback: Return to Dashboard if opened directly with no history
            const baseUri = window.location.pathname.includes('/islamia%20college') ? '/islamia%20college' : '';
            window.location.href = baseUri + '/index.php';
        }
    });
}

/**
 * Theme & Color Palette Management
 */
function initTheme() {
    const savedTheme = localStorage.getItem('ict_theme') || 'light';
    setTheme(savedTheme, false);
}

function setTheme(themeName, showNotification = true) {
    if (!themeName) themeName = 'light';
    document.documentElement.setAttribute('data-theme', themeName);
    localStorage.setItem('ict_theme', themeName);

    // Update active state in theme-picker cards if present on page
    document.querySelectorAll('.theme-card-option').forEach(card => {
        const t = card.getAttribute('data-theme-key');
        card.classList.toggle('active', t === themeName);
    });

    // Update theme toggle buttons title and attributes
    document.querySelectorAll('#sidebar-theme-toggle, .theme-toggle-btn').forEach(btn => {
        const isDark = themeName === 'dark';
        btn.setAttribute('title', isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme');
        btn.setAttribute('aria-label', isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme');
    });

    if (showNotification && typeof showToast === 'function') {
        const themeLabels = {
            'light': 'Suggested Light (Default)',
            'clean-blue': 'Clean Blue',
            'royal-blue': 'Royal Blue',
            'teal': 'Teal Academic',
            'purple': 'Purple Academic',
            'navy-cyan': 'Navy + Cyan',
            'slate-blue': 'Slate + Blue',
            'green': 'Green Academic',
            'warm': 'Warm Professional',
            'dark': 'Classic Dark Navy'
        };
        showToast(`Theme applied: ${themeLabels[themeName] || themeName}`, 'info');
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? (localStorage.getItem('ict_light_theme_choice') || 'light') : 'dark';
    if (current !== 'dark') {
        localStorage.setItem('ict_light_theme_choice', current);
    }
    setTheme(next);
}

window.initTheme = initTheme;
window.setTheme = setTheme;
window.toggleTheme = toggleTheme;

