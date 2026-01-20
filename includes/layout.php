<?php
// Layout Helper Function
function renderPageLayout($title, $user, $current_page = '') {
    // Set current page for sidebar
    $_GET['page'] = $current_page ?: $_GET['page'] ?? 'dashboard';
    
    $user_initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
    ?>
    <div class="app-wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="header">
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                    <span>☰</span>
                </button>
                <h1 class="header-title"><?php echo htmlspecialchars($title); ?></h1>
                <div class="header-actions">
                    <!-- Global Search -->
                    <div class="header-search">
                        <div class="search-container">
                            <input type="text" 
                                   id="globalSearch" 
                                   class="search-input" 
                                   placeholder="Search permits, businesses..."
                                   autocomplete="off">
                            <button type="button" class="search-btn" onclick="performGlobalSearch()">
                                🔍
                            </button>
                        </div>
                        <div id="searchResults" class="search-results" style="display: none;"></div>
                    </div>
                    
                    <div class="header-user">
                        <div class="header-user-avatar"><?php echo $user_initials; ?></div>
                    </div>
                </div>
            </header>
            
            <div class="content">
    <?php
}

function closePageLayout() {
    ?>
            </div>
        </div>
    </div>
    
    <!-- Global Search JavaScript -->
    <script>
        let searchTimeout;
        let currentSearchRequest = null;
        
        // Global search functionality
        function performGlobalSearch() {
            const searchInput = document.getElementById('globalSearch');
            const searchResults = document.getElementById('searchResults');
            const searchTerm = searchInput.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                hideSearchResults();
                return;
            }
            
            // Show loading state
            showSearchLoading();
            
            // Debounce search
            searchTimeout = setTimeout(() => {
                executeSearch(searchTerm);
            }, 300);
        }
        
        function executeSearch(searchTerm) {
            // Cancel previous request if exists
            if (currentSearchRequest) {
                currentSearchRequest.abort();
            }
            
            currentSearchRequest = new XMLHttpRequest();
            currentSearchRequest.open('GET', `api/global_search.php?q=${encodeURIComponent(searchTerm)}`, true);
            
            currentSearchRequest.onload = function() {
                if (currentSearchRequest.status === 200) {
                    try {
                        const response = JSON.parse(currentSearchRequest.responseText);
                        if (response.success) {
                            displaySearchResults(response.results);
                        } else {
                            showSearchError(response.message);
                        }
                    } catch (e) {
                        showSearchError('Invalid response from server');
                    }
                } else {
                    showSearchError('Search failed');
                }
                currentSearchRequest = null;
            };
            
            currentSearchRequest.onerror = function() {
                showSearchError('Network error');
                currentSearchRequest = null;
            };
            
            currentSearchRequest.send();
        }
        
        function displaySearchResults(results) {
            const searchResults = document.getElementById('searchResults');
            
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
            } else {
                let html = '';
                results.forEach(result => {
                    const icon = result.type === 'permit' ? '📋' : '🏢';
                    html += `
                        <a href="${result.url}" class="search-result-item" onclick="hideSearchResults()">
                            <div class="search-result-title">${icon} ${result.title}</div>
                            <div class="search-result-subtitle">${result.subtitle}</div>
                        </a>
                    `;
                });
                searchResults.innerHTML = html;
            }
            
            searchResults.style.display = 'block';
        }
        
        function showSearchLoading() {
            const searchResults = document.getElementById('searchResults');
            searchResults.innerHTML = '<div class="search-loading">⏳ Searching...</div>';
            searchResults.style.display = 'block';
        }
        
        function showSearchError(message) {
            const searchResults = document.getElementById('searchResults');
            searchResults.innerHTML = `<div class="search-no-results">❌ ${message}</div>`;
            searchResults.style.display = 'block';
        }
        
        function hideSearchResults() {
            const searchResults = document.getElementById('searchResults');
            searchResults.style.display = 'none';
        }
        
        // Initialize search when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                // Search on input
                searchInput.addEventListener('input', performGlobalSearch);
                
                // Search on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performGlobalSearch();
                    }
                });
                
                // Hide results when clicking outside
                document.addEventListener('click', function(e) {
                    const searchContainer = document.querySelector('.search-container');
                    if (!searchContainer.contains(e.target)) {
                        hideSearchResults();
                    }
                });
                
                // Keyboard navigation
                searchInput.addEventListener('keydown', function(e) {
                    const searchResults = document.getElementById('searchResults');
                    const items = searchResults.querySelectorAll('.search-result-item');
                    let currentIndex = -1;
                    
                    // Find current selected item
                    items.forEach((item, index) => {
                        if (item.classList.contains('selected')) {
                            currentIndex = index;
                        }
                    });
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        items[currentIndex]?.classList.remove('selected');
                        currentIndex = Math.min(currentIndex + 1, items.length - 1);
                        items[currentIndex]?.classList.add('selected');
                        items[currentIndex]?.scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        items[currentIndex]?.classList.remove('selected');
                        currentIndex = Math.max(currentIndex - 1, 0);
                        items[currentIndex]?.classList.add('selected');
                        items[currentIndex]?.scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'Escape') {
                        hideSearchResults();
                        searchInput.blur();
                    }
                });
            }
        });
        
        // Add CSS for selected search item
        const style = document.createElement('style');
        style.textContent = `
            .search-result-item.selected {
                background: var(--bg-sidebar-active) !important;
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <script src="assets/js/main.js"></script>
    <?php
}
?>
