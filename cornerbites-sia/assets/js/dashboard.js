
// Dashboard JavaScript untuk HPP Calculator
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard features
    initQuickActions();
    initAlerts();
    initRankingControls();

    console.log('HPP Dashboard loaded successfully');
});

function initQuickActions() {
    // Add hover effects and analytics tracking for quick action buttons
    const quickActions = document.querySelectorAll('.transform.hover\\:scale-105');

    quickActions.forEach(action => {
        action.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.classList.add('absolute', 'bg-white', 'bg-opacity-30', 'rounded-full', 'animate-ping');
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.left = '50%';
            ripple.style.top = '50%';
            ripple.style.transform = 'translate(-50%, -50%)';
            
            this.style.position = 'relative';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

function initAlerts() {
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.bg-yellow-50, .bg-red-50');

    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0.8';
        }, 5000);
    });
}

function initRankingControls() {
    // Initialize search input
    const searchInput = document.getElementById('search_ranking_input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchRanking(this.value);
            }
        });
    }

    // Initialize limit selector
    const limitSelect = document.getElementById('ranking_limit_select');
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            updateRankingLimit(this.value);
        });
    }
}

// Global variables for search and pagination state
let currentRankingPage = 1;
let currentRankingLimit = 10;
let currentSearchTerm = '';
let searchTimeout;

// Function to load ranking data with AJAX
function loadRankingData(page = 1, limit = null, search = null) {
    if (limit !== null) currentRankingLimit = limit;
    if (search !== null) currentSearchTerm = search;
    currentRankingPage = page;
    
    const container = document.getElementById('ranking-container');
    if (!container) {
        console.error('Ranking container not found');
        return;
    }

    // Show loading with better UX
    const loadingHTML = `
        <div class="flex flex-col justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600 mb-3"></div>
            <span class="text-gray-600 text-sm">Memuat ranking produk...</span>
            <div class="mt-2 text-xs text-gray-400">
                ${currentSearchTerm ? `Mencari: "${currentSearchTerm}"` : 'Menampilkan semua produk'}
            </div>
        </div>
    `;
    container.innerHTML = loadingHTML;

    const params = new URLSearchParams({
        ajax: 'ranking',
        ranking_page: currentRankingPage,
        ranking_limit: currentRankingLimit,
        search_ranking: currentSearchTerm
    });

    fetch(`dashboard.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
            
            // Re-initialize controls after AJAX load
            const newSearchInput = document.getElementById('search_ranking_input');
            const newLimitSelect = document.getElementById('ranking_limit_select');
            
            if (newSearchInput) {
                newSearchInput.value = currentSearchTerm;
                newSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchRanking(this.value);
                    }
                });
            }
            
            if (newLimitSelect) {
                newLimitSelect.value = currentRankingLimit;
                newLimitSelect.addEventListener('change', function() {
                    updateRankingLimit(this.value);
                });
            }
        })
        .catch(error => {
            console.error('Error loading ranking data:', error);
            container.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <div class="mb-2">⚠️ Error loading ranking data</div>
                    <button onclick="loadRankingData(${currentRankingPage}, ${currentRankingLimit}, '${currentSearchTerm}')" 
                            class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm transition-colors">
                        Coba Lagi
                    </button>
                </div>
            `;
        });
}

function updateRankingLimit(newLimit) {
    currentRankingLimit = parseInt(newLimit);
    loadRankingData(1, currentRankingLimit, currentSearchTerm);
}

function searchRanking(searchTerm) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentSearchTerm = searchTerm.trim();
        loadRankingData(1, currentRankingLimit, currentSearchTerm);
    }, 500); // Debounce search for 500ms
}

// Make functions global
window.loadRankingData = loadRankingData;
window.updateRankingLimit = updateRankingLimit;
window.searchRanking = searchRanking;

// Utility function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Function to refresh dashboard data (for future AJAX implementation)
function refreshDashboard() {
    console.log('Refreshing dashboard data...');
    location.reload();
}

// Auto-refresh functionality (optional)
function enableAutoRefresh(intervalMinutes = 5) {
    setInterval(() => {
        // Only refresh ranking data, not the whole page
        loadRankingData(currentRankingPage, currentRankingLimit, currentSearchTerm);
    }, intervalMinutes * 60 * 1000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + R to refresh ranking
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        loadRankingData(currentRankingPage, currentRankingLimit, currentSearchTerm);
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.getElementById('search_ranking_input');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            searchRanking('');
        }
    }
});
