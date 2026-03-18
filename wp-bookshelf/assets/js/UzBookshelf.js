// ============================================================
// UZ Bookshelf — WordPress Plugin版 v1.1
// Pure JS (no JSX/Babel) — React.createElement only
// WP REST API対応 + 記事詳細ビュー, モーダル内関連本,
// 記事カテゴリフィルタ, 持続フィルタ, キーボード拡充
// ============================================================

var h = React.createElement;

// WordPress config (injected by wp_localize_script or inline script)
var _uzConfig = typeof uzBookshelfConfig !== 'undefined' ? uzBookshelfConfig : {
  apiBase: '',
  nonce: '',
  coversBase: 'covers/',
};

function uzFetch(endpoint) {
  var url = _uzConfig.apiBase ? _uzConfig.apiBase + endpoint : endpoint;
  // Cache busting to avoid server-level caching
  var sep = url.indexOf('?') >= 0 ? '&' : '?';
  url += sep + '_t=' + Date.now();
  var opts = {};
  if (_uzConfig.nonce) {
    opts.headers = { 'X-WP-Nonce': _uzConfig.nonce };
  }
  return fetch(url, opts).then(function(r) { return r.json(); });
}

// ---- ユーティリティ ----
function stableHash(str) {
  var hash = 2166136261;
  for (var i = 0; i < str.length; i++) {
    hash ^= str.charCodeAt(i);
    hash = Math.imul(hash, 16777619);
  }
  return Math.abs(hash);
}

function spineColorFromTitle(title) {
  var hash = stableHash(title);
  var hue = hash % 360;
  var s = 25 + (stableHash(title + 'sat') % 20);
  var l = 32 + (stableHash(title + 'lit') % 18);
  return { h: hue, s: s, l: l, css: 'hsl(' + hue + ' ' + s + '% ' + l + '%)' };
}

function spineGradient(title) {
  var c = spineColorFromTitle(title);
  var light = 'hsl(' + c.h + ' ' + c.s + '% ' + (c.l + 12) + '%)';
  var dark = 'hsl(' + c.h + ' ' + c.s + '% ' + (c.l - 8) + '%)';
  return 'linear-gradient(135deg, ' + light + ' 0%, ' + c.css + ' 50%, ' + dark + ' 100%)';
}

function truncate(str, len) {
  if (!str) return '';
  return str.length > len ? str.substring(0, len) + '\u2026' : str;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  var m = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/);
  if (m) return m[3] + '.' + m[1] + '.' + m[2];
  return dateStr;
}

function renderStars(rating) {
  if (!rating || rating === '0') return null;
  var num = parseFloat(rating);
  var full = Math.floor(num);
  var half = num - full >= 0.5;
  var stars = [];
  for (var i = 0; i < full; i++) stars.push('\u2605');
  if (half) stars.push('\u2606');
  return stars.join('');
}

// ---- 判型サイズマッピング ----
var FORMAT_SIZES = {
  bunko:     { w: 82, h: 118, label: '文庫' },
  comic:     { w: 96, h: 138, label: 'コミック' },
  shinsho:   { w: 82, h: 132, label: '新書' },
  tankobon:  { w: 96, h: 138, label: '単行本' },
  hardcover: { w: 110, h: 158, label: 'ハードカバー' },
  poster:    { w: 100, h: 148, label: '映画' },
  disc:      { w: 110, h: 110, label: 'ディスク' },
  standard:  { w: 96, h: 140, label: '' },
};

function detectFormat(name) {
  if (!name) return 'standard';
  if (/文庫/.test(name)) return 'bunko';
  if (/コミック|漫画|マンガ|全\d+巻/.test(name)) return 'comic';
  if (/新書/.test(name)) return 'shinsho';
  if (/Blu-ray|ブルーレイ|DVD|BD/.test(name)) return 'disc';
  if (/CD|レコード|vinyl|Vinyl/.test(name)) return 'disc';
  if (/ハードカバー|単行本/.test(name)) return 'tankobon';
  return 'standard';
}

function getBookDimensions(item) {
  var fmt = item.format || detectFormat(item.fullTitle || item.title);
  var base = FORMAT_SIZES[fmt] || FORMAT_SIZES.standard;
  return {
    width: Math.round(base.w),
    height: Math.round(base.h),
    format: fmt,
    label: base.label,
    thickness: (fmt === 'disc' || fmt === 'poster') ? 6 : (fmt === 'bunko' ? 14 : 18 + (stableHash(item.title || '') % 12)),
  };
}

// ---- お気に入りヘルパー ----
function loadFavorites() {
  try { return JSON.parse(localStorage.getItem('uz-favorites') || '[]'); }
  catch(e) { return []; }
}
function saveFavorites(favs) {
  localStorage.setItem('uz-favorites', JSON.stringify(favs));
}
function favKey(item) {
  return item.isbn || item.id || ((item.source || '') + '_' + (item.fullTitle || item.title));
}

// ============================================================
// 表紙コンポーネント — 3D
// ============================================================
var BookFace = React.memo(function(props) {
  var item = props.item, isHighlighted = props.isHighlighted, isFav = props.isFav;
  var onClick = props.onClick, onMouseEnter = props.onMouseEnter, onMouseLeave = props.onMouseLeave;
  var dim = getBookDimensions(item);
  var imgLoadedRef = React.useState(false);
  var imgLoaded = imgLoadedRef[0], setImgLoaded = imgLoadedRef[1];
  var isDisc = dim.format === 'disc';
  var isPoster = dim.format === 'poster';
  var cls = 'uz-book' + (isHighlighted ? ' uz-highlight' : '') + (isDisc ? ' uz-book--disc' : '') + (isPoster ? ' uz-book--poster' : '');

  var children = [];
  var frontChildren = [];

  if (item.coverUrl) {
    frontChildren.push(
      h('img', {
        key: 'img',
        className: 'uz-book__img' + (imgLoaded ? ' loaded' : ''),
        src: item.coverUrl, alt: '',
        onLoad: function() { setImgLoaded(true); },
        onError: function(e) { e.target.style.display = 'none'; }
      })
    );
  }
  if (!item.coverUrl || !imgLoaded) {
    frontChildren.push(
      h('div', { key: 'ph', className: 'uz-book__placeholder', style: { background: spineGradient(item.fullTitle || item.title) } },
        h('span', { className: 'uz-book__placeholderText' }, truncate(item.fullTitle || item.title, 20)),
        h('span', { className: 'uz-book__placeholderAuthor' }, item.author || item.fullAuthor || '')
      )
    );
  }
  frontChildren.push(h('div', { key: 'gloss', className: 'uz-book__gloss' }));

  var bodyChildren = [
    h('div', { key: 'front', className: 'uz-book__front' }, frontChildren),
    h('div', { key: 'spine', className: 'uz-book__spine', style: { background: spineGradient(item.fullTitle || item.title), width: dim.thickness } })
  ];
  if (!isDisc && !isPoster) {
    bodyChildren.push(h('div', { key: 'pages', className: 'uz-book__pages', style: { height: dim.thickness } }));
  }
  if (item.reviewAverage && item.reviewAverage !== '0') {
    bodyChildren.push(h('div', { key: 'badge', className: 'uz-book__badge' }, renderStars(item.reviewAverage)));
  }
  if (isFav) {
    bodyChildren.push(h('div', { key: 'fav', className: 'uz-book__favBadge' }, '\u2665'));
  }

  return h('a', {
    className: cls, href: '#',
    onClick: onClick, onMouseEnter: onMouseEnter, onMouseLeave: onMouseLeave,
    style: { width: dim.width, height: dim.height }
  },
    h('div', { className: 'uz-book__body' }, bodyChildren),
    h('div', { className: 'uz-book__shadow' })
  );
});

// ============================================================
// 背表紙コンポーネント — リアル質感
// ============================================================
function spineDecor(title) {
  var hash = stableHash(title);
  var variant = hash % 5;
  var c = spineColorFromTitle(title);
  var gold = 'hsl(' + (40 + (hash % 20)) + ' ' + (50 + (hash % 20)) + '% ' + (55 + (hash % 15)) + '%)';
  return { variant: variant, gold: gold };
}

var BookSpine = React.memo(function(props) {
  var item = props.item, isHighlighted = props.isHighlighted;
  var onClick = props.onClick, onMouseEnter = props.onMouseEnter, onMouseLeave = props.onMouseLeave;
  var dim = getBookDimensions(item);
  var isDisc = dim.format === 'disc' || dim.format === 'poster';
  var thickness = isDisc ? 8 : dim.thickness;
  var decor = spineDecor(item.fullTitle || item.title);
  var title = item.fullTitle || item.title || '';
  var author = item.fullAuthor || item.author || '';
  var maxTitleChars = Math.floor(dim.height / 10);
  var maxAuthorChars = Math.floor(dim.height / 16);
  var cls = 'uz-spine2 uz-spine2--v' + decor.variant + (isHighlighted ? ' uz-highlight' : '') + (isDisc ? ' uz-spine2--disc' : '');

  var textChildren = [
    h('span', { key: 't', className: 'uz-spine2__title', style: { color: decor.gold } }, truncate(title, maxTitleChars))
  ];
  if (author) {
    textChildren.push(h('span', { key: 'a', className: 'uz-spine2__author' }, truncate(author, maxAuthorChars)));
  }

  return h('a', {
    className: cls, href: '#',
    onClick: onClick, onMouseEnter: onMouseEnter, onMouseLeave: onMouseLeave,
    style: { width: thickness, height: dim.height }
  },
    h('div', { className: 'uz-spine2__body', style: { background: spineGradient(title) } },
      h('div', { className: 'uz-spine2__texture' }),
      h('div', { className: 'uz-spine2__bandTop', style: { background: decor.gold } }),
      h('div', { className: 'uz-spine2__text' }, textChildren),
      h('div', { className: 'uz-spine2__bandBottom', style: { background: decor.gold } }),
      h('div', { className: 'uz-spine2__pub', style: { borderColor: decor.gold } },
        h('span', { style: { color: decor.gold } }, (author || title).charAt(0))
      ),
      h('div', { className: 'uz-spine2__edge' }),
      h('div', { className: 'uz-spine2__gloss' })
    )
  );
});

// ============================================================
// メインコンポーネント
// ============================================================
function UzBookshelf() {
  var useState = React.useState, useEffect = React.useEffect, useMemo = React.useMemo, useCallback = React.useCallback, useRef = React.useRef;

  var _mode = useState('uz'); var mode = _mode[0]; var setMode = _mode[1];
  var _rakuten = useState(null); var rakutenData = _rakuten[0]; var setRakutenData = _rakuten[1];
  var _uz = useState(null); var uzData = _uz[0]; var setUzData = _uz[1];
  var _genreMap = useState({}); var genreMap = _genreMap[0]; var setGenreMap = _genreMap[1];
  var _modal = useState(null); var modal = _modal[0]; var setModal = _modal[1];
  var _tooltip = useState(null); var tooltip = _tooltip[0]; var setTooltip = _tooltip[1];
  var _activeShelf = useState(null); var activeShelf = _activeShelf[0]; var setActiveShelf = _activeShelf[1];
  var _showArticles = useState(false); var showArticles = _showArticles[0]; var setShowArticles = _showArticles[1];
  var _searchQuery = useState(''); var searchQuery = _searchQuery[0]; var setSearchQuery = _searchQuery[1];
  var _shelfIndex = useState(0); var shelfIndex = _shelfIndex[0]; var setShelfIndex = _shelfIndex[1];
  var tooltipTimeoutRef = useRef(null);
  var _bookSearch = useState(''); var bookSearch = _bookSearch[0]; var setBookSearch = _bookSearch[1];
  var _selectedGenre = useState(null); var selectedGenre = _selectedGenre[0]; var setSelectedGenre = _selectedGenre[1];
  var _loadError = useState(null); var loadError = _loadError[0]; var setLoadError = _loadError[1];
  var _favorites = useState(loadFavorites); var favorites = _favorites[0]; var setFavorites = _favorites[1];
  var _showFavorites = useState(false); var showFavorites = _showFavorites[0]; var setShowFavorites = _showFavorites[1];
  var _selectedArticle = useState(null); var selectedArticle = _selectedArticle[0]; var setSelectedArticle = _selectedArticle[1];
  var _activeCategory = useState(null); var activeCategory = _activeCategory[0]; var setActiveCategory = _activeCategory[1];
  var _activeTheme = useState(null); var activeTheme = _activeTheme[0]; var setActiveTheme = _activeTheme[1];
  var _filterByArticle = useState(null); var filterByArticle = _filterByArticle[0]; var setFilterByArticle = _filterByArticle[1];

  useEffect(function() { saveFavorites(favorites); }, [favorites]);

  var toggleFavorite = function(item) {
    var key = favKey(item);
    setFavorites(function(prev) {
      return prev.includes(key) ? prev.filter(function(k) { return k !== key; }) : prev.concat([key]);
    });
  };
  var isFavorite = function(item) { return favorites.includes(favKey(item)); };

  var handleMouseEnter = function(e, item) {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    var el = e.currentTarget;
    tooltipTimeoutRef.current = setTimeout(function() {
      var rect = el.getBoundingClientRect();
      setTooltip({ x: rect.left + rect.width / 2, y: rect.top, item: item });
    }, 350);
  };
  var handleMouseLeave = function() {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    setTooltip(null);
  };

  // --- 楽天データ ---
  useEffect(function() {
    setGenreMap({ '001005': 'テクノロジー', '001006': 'ビジネス', '001010': 'カルチャー' });
  }, []);

  useEffect(function() {
    if (Object.keys(genreMap).length === 0) return;
    Promise.all([
      uzFetch('/rakuten/001005'),
      uzFetch('/rakuten/001006'),
      uzFetch('/rakuten/001010'),
    ]).then(function(results) {
      var grouped = { tech: [], biz: [], culture: [] };
      [{ json: results[0], shelf: 'tech' }, { json: results[1], shelf: 'biz' }, { json: results[2], shelf: 'culture' }]
        .forEach(function(entry) {
          (entry.json.Items || []).forEach(function(item) {
            var b = item.Item;
            grouped[entry.shelf].push({
              id: b.isbn, title: truncate(b.title, 20), fullTitle: b.title,
              author: truncate(b.author, 16), fullAuthor: b.author,
              coverUrl: b.largeImageUrl, url: b.itemUrl,
              affiliateUrl: b.affiliateUrl || '', isbn: b.isbn,
              price: b.itemPrice, reviewAverage: b.reviewAverage,
              reviewCount: b.reviewCount, caption: b.itemCaption || '',
              publisher: b.publisherName || '', salesDate: b.salesDate || '',
              genreId: b.booksGenreId || '', shelf: entry.shelf, source: 'rakuten',
              format: detectFormat(b.title),
            });
          });
        });
      setRakutenData([
        { id: 'tech', title: 'テクノロジー', books: grouped.tech },
        { id: 'biz', title: 'ビジネス', books: grouped.biz },
        { id: 'culture', title: 'カルチャー', books: grouped.culture },
      ]);
    }).catch(function(e) { console.error('Book load error:', e); setLoadError('書籍データの読み込みに失敗しました'); });
  }, [genreMap]);

  // --- uzデータ ---
  useEffect(function() {
    uzFetch('/shelves').then(function(data) {
      // Enrich articles with thumbnail URLs from WP REST API
      if (data && data.articles && data.articles.length > 0) {
        var needsThumbs = data.articles.some(function(a) { return !a.thumbnailUrl; });
        if (needsThumbs) {
          // Fetch posts with embedded featured media from WP standard API
          var wpBase = _uzConfig.apiBase ? _uzConfig.apiBase.replace(/\/uz-bookshelf\/v1$/, '') : '/wp-json';
          wpBase = wpBase.replace(/\/uz-bookshelf\/v1$/, '');
          var wpUrl = wpBase.replace(/\/uz-bookshelf\/v1/, '') + '/wp/v2/posts?per_page=100&_fields=id,slug,featured_media,_links&_embed=wp:featuredmedia';
          fetch(wpUrl).then(function(r) { return r.json(); }).then(function(posts) {
            var thumbMap = {};
            posts.forEach(function(p) {
              var embedded = p._embedded && p._embedded['wp:featuredmedia'];
              if (embedded && embedded[0] && embedded[0].source_url) {
                thumbMap[p.slug] = embedded[0].source_url;
              } else if (embedded && embedded[0] && embedded[0].media_details && embedded[0].media_details.sizes) {
                var sizes = embedded[0].media_details.sizes;
                var url = (sizes.medium && sizes.medium.source_url) || (sizes.thumbnail && sizes.thumbnail.source_url) || (sizes.full && sizes.full.source_url);
                if (url) thumbMap[p.slug] = url;
              }
            });
            data.articles = data.articles.map(function(a) {
              if (!a.thumbnailUrl && thumbMap[a.id]) {
                return Object.assign({}, a, { thumbnailUrl: thumbMap[a.id] });
              }
              return a;
            });
            setUzData(data);
          }).catch(function() { setUzData(data); });
        } else {
          setUzData(data);
        }
      } else {
        setUzData(data);
      }
    }).catch(function(e) { console.error('uz data:', e); setLoadError('本棚データの読み込みに失敗しました'); });
  }, []);

  // --- 棚データ構築 ---
  var rakutenShelves = useMemo(function() {
    if (!rakutenData) return [];
    return rakutenData.map(function(s) {
      var books = s.books.slice(0, 30).map(function(b) {
        return Object.assign({}, b, { type: b.type || (b.coverUrl ? 'featured' : 'spine') });
      });
      return Object.assign({}, s, { mixedBooks: books });
    });
  }, [rakutenData]);

  var uzShelves = useMemo(function() {
    if (!uzData) return [];
    return uzData.shelves.filter(function(s) { return s.items.length > 0; }).map(function(s) {
      var isFilm = s.id === 'film';
      var items = s.items.map(function(item) {
        var displayType = item.type || 'product';
        if (displayType !== 'spine') {
          displayType = item.coverUrl ? 'featured' : 'spine';
        }
        return Object.assign({}, item, {
          type: displayType, source: 'uz', shelfId: s.id,
          format: isFilm ? 'poster' : (item.format || detectFormat(item.fullTitle || item.title)),
        });
      });
      return Object.assign({}, s, { mixedItems: items });
    });
  }, [uzData]);

  var filteredArticles = useMemo(function() {
    if (!uzData) return [];
    var articles = uzData.articles;
    if (searchQuery) {
      var q = searchQuery.toLowerCase();
      articles = articles.filter(function(a) {
        return a.title.toLowerCase().includes(q) || a.categories.some(function(c) { return c.toLowerCase().includes(q); });
      });
    }
    if (activeShelf && activeShelf !== 'all') articles = articles.filter(function(a) { return a.shelf === activeShelf; });
    if (activeCategory) articles = articles.filter(function(a) { return a.categories && a.categories.includes(activeCategory); });
    if (activeTheme) articles = articles.filter(function(a) { return a.themes && a.themes.includes(activeTheme); });
    return articles;
  }, [uzData, searchQuery, activeShelf, activeCategory, activeTheme]);

  var allCategories = useMemo(function() {
    if (!uzData) return [];
    var cats = new Set();
    uzData.articles.forEach(function(a) { (a.categories || []).forEach(function(c) { if (c) cats.add(c); }); });
    return Array.from(cats).sort();
  }, [uzData]);

  var allThemes = useMemo(function() {
    if (!uzData) return [];
    var themeMap = {};
    uzData.articles.forEach(function(a) {
      (a.themes || []).forEach(function(t) {
        if (t) themeMap[t] = (themeMap[t] || 0) + 1;
      });
    });
    return Object.keys(themeMap).sort(function(a, b) { return themeMap[b] - themeMap[a]; });
  }, [uzData]);

  // テーマ名のマッピング（slug -> 日本語名）
  var _themeNames = useState({}); var themeNames = _themeNames[0]; var setThemeNames = _themeNames[1];
  useEffect(function() {
    uzFetch('/themes').then(function(themes) {
      var map = {};
      themes.forEach(function(t) { map[t.slug] = t.name; });
      setThemeNames(map);
    }).catch(function() {});
  }, []);

  // --- 棚ナビゲーション ---
  var allShelves = useMemo(function() {
    return mode === 'uz' ? uzShelves : rakutenShelves;
  }, [mode, uzShelves, rakutenShelves]);

  var currentShelf = allShelves[shelfIndex] || null;

  var goToShelf = function(idx) { setShelfIndex(idx); setSelectedGenre(null); window.scrollTo({ top: 0, behavior: 'smooth' }); };
  var prevShelf = function() { goToShelf(Math.max(0, shelfIndex - 1)); };
  var nextShelf = function() { goToShelf(Math.min(allShelves.length - 1, shelfIndex + 1)); };

  var switchMode = function(newMode) {
    setMode(newMode); setShelfIndex(0); setShowArticles(false); setShowFavorites(false);
    setBookSearch(''); setSelectedGenre(null); setSelectedArticle(null); setFilterByArticle(null);
  };

  var openModal = function(item, e) { if (e) e.preventDefault(); setModal(item); };
  var closeModal = function() { setModal(null); };
  var openArticleDetail = function(article) { setSelectedArticle(article); };
  var backToArticleList = function() { setSelectedArticle(null); };
  var showArticleBooksOnShelf = function(articleId) { setShowArticles(false); setSelectedArticle(null); setFilterByArticle(articleId); };

  var openArticleFromModal = function(articleId) {
    if (!uzData) return;
    var art = uzData.articles.find(function(a) { return a.id === articleId; });
    if (art) { closeModal(); setShowArticles(true); setShowFavorites(false); setSelectedArticle(art); }
  };

  var getArticleBooks = useCallback(function(articleId) {
    if (!uzData) return [];
    var books = [];
    uzData.shelves.forEach(function(s) {
      s.items.forEach(function(item) {
        if (item.articleId === articleId) {
          var displayType = item.type || 'product';
          if (displayType !== 'spine') displayType = item.coverUrl ? 'featured' : 'spine';
          books.push(Object.assign({}, item, {
            type: displayType, source: 'uz', shelfId: s.id,
            format: s.id === 'film' ? 'poster' : (item.format || detectFormat(item.fullTitle || item.title)),
          }));
        }
      });
    });
    return books;
  }, [uzData]);

  var shelfIcons = { books: '\uD83D\uDCDA', manga: '\uD83D\uDCD6', film: '\uD83C\uDFAC', music: '\uD83C\uDFB5', tech: '\uD83D\uDCBB', biz: '\uD83D\uDCBC', culture: '\uD83C\uDF0D' };

  // --- キーボードナビゲーション ---
  useEffect(function() {
    var handler = function(e) {
      if (e.key === 'Escape') {
        if (modal) { closeModal(); return; }
        if (selectedArticle) { backToArticleList(); return; }
        if (filterByArticle) { setFilterByArticle(null); return; }
      }
      if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) return;
      if (e.key === 'ArrowLeft' && !showArticles && !showFavorites) { prevShelf(); e.preventDefault(); }
      if (e.key === 'ArrowRight' && !showArticles && !showFavorites) { nextShelf(); e.preventDefault(); }
    };
    document.addEventListener('keydown', handler);
    return function() { document.removeEventListener('keydown', handler); };
  }, [modal, selectedArticle, filterByArticle, shelfIndex, allShelves.length, showArticles, showFavorites]);

  var currentGenres = useMemo(function() {
    // Rakuten shelves are already grouped by genre (tech/biz/culture),
    // sub-genre filter is not needed
    return [];
  }, [mode, currentShelf, genreMap]);

  var currentItems = useMemo(function() {
    if (!currentShelf) return [];
    var items = currentShelf.mixedItems || currentShelf.mixedBooks || [];
    if (selectedGenre && mode === 'rakuten') items = items.filter(function(b) { return b.genreId === selectedGenre; });
    if (filterByArticle && mode === 'uz') items = items.filter(function(b) { return b.articleId === filterByArticle; });
    return items;
  }, [currentShelf, selectedGenre, mode, filterByArticle]);

  var filterArticleTitle = useMemo(function() {
    if (!filterByArticle || !uzData) return '';
    var art = uzData.articles.find(function(a) { return a.id === filterByArticle; });
    return art ? art.title : filterByArticle;
  }, [filterByArticle, uzData]);

  // uz: local search
  var uzSearchResults = useMemo(function() {
    if (mode !== 'uz' || !bookSearch.trim()) return null;
    var q = bookSearch.toLowerCase();
    var allItems = [];
    uzShelves.forEach(function(s) {
      (s.mixedItems || s.mixedBooks || []).forEach(function(item) {
        if ((item.fullTitle || item.title || '').toLowerCase().includes(q) ||
            (item.fullAuthor || item.author || '').toLowerCase().includes(q) ||
            (item.articleTitle || '').toLowerCase().includes(q)) allItems.push(item);
      });
    });
    return allItems;
  }, [bookSearch, mode, uzShelves]);

  // rakuten: API search with debounce
  var _rakutenSearchResults = useState(null); var rakutenSearchResults = _rakutenSearchResults[0]; var setRakutenSearchResults = _rakutenSearchResults[1];
  var _rakutenSearching = useState(false); var rakutenSearching = _rakutenSearching[0]; var setRakutenSearching = _rakutenSearching[1];
  var rakutenSearchTimer = useRef(null);

  useEffect(function() {
    if (mode !== 'rakuten' || !bookSearch.trim()) {
      setRakutenSearchResults(null);
      setRakutenSearching(false);
      return;
    }
    setRakutenSearching(true);
    if (rakutenSearchTimer.current) clearTimeout(rakutenSearchTimer.current);
    rakutenSearchTimer.current = setTimeout(function() {
      uzFetch('/rakuten-search?q=' + encodeURIComponent(bookSearch.trim()))
        .then(function(data) {
          var items = (data.Items || []).map(function(entry) {
            var b = entry.Item;
            return {
              id: b.isbn, title: truncate(b.title, 20), fullTitle: b.title,
              author: truncate(b.author, 16), fullAuthor: b.author,
              coverUrl: b.largeImageUrl, url: b.itemUrl,
              isbn: b.isbn, price: b.itemPrice,
              reviewAverage: b.reviewAverage, reviewCount: b.reviewCount,
              caption: b.itemCaption || '', publisher: b.publisherName || '',
              salesDate: b.salesDate || '', source: 'rakuten',
              format: detectFormat(b.title),
            };
          });
          setRakutenSearchResults(items);
          setRakutenSearching(false);
        })
        .catch(function() { setRakutenSearchResults([]); setRakutenSearching(false); });
    }, 500);
    return function() { if (rakutenSearchTimer.current) clearTimeout(rakutenSearchTimer.current); };
  }, [bookSearch, mode]);

  var searchResults = mode === 'uz' ? uzSearchResults : (mode === 'rakuten' && bookSearch.trim() ? rakutenSearchResults : null);

  var favoriteItems = useMemo(function() {
    if (!showFavorites) return null;
    var all = [];
    uzShelves.forEach(function(s) { (s.mixedItems || []).forEach(function(item) { if (isFavorite(item)) all.push(item); }); });
    rakutenShelves.forEach(function(s) { (s.mixedBooks || []).forEach(function(item) { if (isFavorite(item)) all.push(item); }); });
    return all;
  }, [showFavorites, favorites, uzShelves, rakutenShelves]);

  var isLoading = !uzData && !loadError;

  // Helper: render a book item (face or spine)
  function renderBookItem(item, idx, prefix) {
    var handlers = {
      onClick: function(e) { openModal(item, e); },
      onMouseEnter: function(e) { handleMouseEnter(e, item); },
      onMouseLeave: handleMouseLeave,
    };
    if (item.type === 'featured') {
      return h(BookFace, Object.assign({ key: prefix + '-' + (item.id || idx) + '-' + idx, item: item, isHighlighted: false, isFav: isFavorite(item) }, handlers));
    }
    return h(BookSpine, Object.assign({ key: prefix + '-' + (item.id || idx) + '-' + idx, item: item, isHighlighted: false }, handlers));
  }

  // ============================================================
  // RENDER
  // ============================================================
  var children = [];

  // Header
  var headerActions = [
    h('button', { key: 'uz', className: 'uz-tabBtn' + (mode === 'uz' && !showArticles && !showFavorites ? ' active' : ''), onClick: function() { switchMode('uz'); } }, 'UZ セレクション'),
    h('button', { key: 'rak', className: 'uz-tabBtn' + (mode === 'rakuten' && !showArticles && !showFavorites ? ' active' : ''), onClick: function() { switchMode('rakuten'); } }, '楽天Books'),
  ];
  if (mode === 'uz') {
    headerActions.push(h('button', { key: 'art', className: 'uz-tabBtn' + (showArticles ? ' active' : ''), onClick: function() { setShowArticles(!showArticles); setShowFavorites(false); } }, '記事一覧'));
  }
  headerActions.push(
    h('button', { key: 'fav', className: 'uz-tabBtn' + (showFavorites ? ' active' : ''), onClick: function() { setShowFavorites(!showFavorites); setShowArticles(false); } },
      '\u2665 お気に入り', favorites.length > 0 ? h('span', { className: 'uz-favCount' }, favorites.length) : null
    )
  );

  children.push(
    h('header', { key: 'header', className: 'uz-header' },
      h('div', null,
        h('div', { className: 'uz-kicker' }, 'UZ MEDIA'),
        h('h1', { className: 'uz-title' }, 'UZ Bookshelf')
      ),
      h('div', { className: 'uz-headerActions' }, headerActions)
    )
  );

  // 検索バー
  if (!showArticles && !showFavorites) {
    children.push(
      h('div', { key: 'search', className: 'uz-bookSearchBar' },
        h('input', {
          className: 'uz-bookSearchInput', type: 'text',
          placeholder: mode === 'uz' ? 'タイトル・著者で検索...' : '楽天ブックスをタイトル・著者で検索...',
          value: bookSearch, onChange: function(e) { setBookSearch(e.target.value); }
        }),
        bookSearch ? h('button', { className: 'uz-bookSearchClear', onClick: function() { setBookSearch(''); } }, '\u2715') : null
      )
    );
  }

  // 棚セレクター
  if (!showArticles && !showFavorites && !searchResults && allShelves.length > 0) {
    children.push(
      h('nav', { key: 'nav', className: 'uz-shelfNav' },
        allShelves.map(function(s, i) {
          return h('button', {
            key: s.id, className: 'uz-shelfNav__btn' + (i === shelfIndex ? ' active' : ''),
            onClick: function() { goToShelf(i); }
          },
            h('span', { className: 'uz-shelfNav__icon' }, shelfIcons[s.id] || ''),
            h('span', { className: 'uz-shelfNav__label' }, s.title)
          );
        })
      )
    );
  }

  // ジャンルフィルタ
  if (!showArticles && !showFavorites && !searchResults && mode === 'rakuten' && currentGenres.length > 1) {
    var genreBtns = [h('button', { key: 'all', className: 'uz-genreBtn' + (!selectedGenre ? ' active' : ''), onClick: function() { setSelectedGenre(null); } }, 'すべて')];
    currentGenres.forEach(function(g) {
      genreBtns.push(h('button', { key: g.id, className: 'uz-genreBtn' + (selectedGenre === g.id ? ' active' : ''), onClick: function() { setSelectedGenre(g.id); } }, g.name));
    });
    children.push(h('div', { key: 'genre', className: 'uz-genreFilter' }, genreBtns));
  }

  // ローディング
  if (isLoading) {
    children.push(h('div', { key: 'loading', className: 'uz-loading' },
      h('div', { className: 'uz-loading__spinner' }),
      h('div', { className: 'uz-loading__text' }, '本棚を読み込み中...')
    ));
  }

  // エラー
  if (loadError) {
    children.push(h('div', { key: 'error', className: 'uz-error' },
      h('div', { className: 'uz-error__icon' }, '!'),
      h('div', { className: 'uz-error__text' }, loadError),
      h('button', { className: 'uz-error__retry', onClick: function() { setLoadError(null); location.reload(); } }, '再読み込み')
    ));
  }

  // 記事一覧パネル
  if (showArticles && uzData && !selectedArticle) {
    var artPanelChildren = [];
    var headChildren = [
      h('h2', { key: 'h' }, 'UZ 記事一覧'),
      h('input', { key: 'search', className: 'uz-searchInput', type: 'text', placeholder: '記事を検索...', value: searchQuery, onChange: function(e) { setSearchQuery(e.target.value); } }),
    ];
    var shelfFilterBtns = [h('button', { key: 'all', className: 'uz-filterBtn' + (!activeShelf || activeShelf === 'all' ? ' active' : ''), onClick: function() { setActiveShelf('all'); } }, 'すべて')];
    uzData.shelves.forEach(function(s) {
      shelfFilterBtns.push(h('button', { key: s.id, className: 'uz-filterBtn' + (activeShelf === s.id ? ' active' : ''), onClick: function() { setActiveShelf(s.id); } }, s.title));
    });
    headChildren.push(h('div', { key: 'sf', className: 'uz-shelfFilter' }, shelfFilterBtns));

    if (allThemes.length > 0) {
      var themeBtns = [h('button', { key: 'all', className: 'uz-filterBtn uz-filterBtn--theme' + (!activeTheme ? ' active' : ''), onClick: function() { setActiveTheme(null); } }, '全テーマ')];
      allThemes.forEach(function(t) {
        var label = themeNames[t] || t;
        themeBtns.push(h('button', { key: t, className: 'uz-filterBtn uz-filterBtn--theme' + (activeTheme === t ? ' active' : ''), onClick: function() { setActiveTheme(t); } }, label));
      });
      headChildren.push(h('div', { key: 'tf', className: 'uz-themeFilter' }, themeBtns));
    }
    artPanelChildren.push(h('div', { key: 'head', className: 'uz-articlesPanelHead' }, headChildren));

    // --- 記事カードグリッド ---
    var artCards = filteredArticles.map(function(art) {
      var cardChildren = [];
      // サムネイル画像
      if (art.thumbnailUrl) {
        cardChildren.push(
          h('div', { key: 'thumb', className: 'uz-artCard__thumb' },
            h('img', { src: art.thumbnailUrl, alt: '', loading: 'lazy' })
          )
        );
      } else {
        cardChildren.push(
          h('div', { key: 'thumb', className: 'uz-artCard__thumb uz-artCard__thumb--placeholder', style: { background: spineGradient(art.title) } },
            h('span', null, art.title.charAt(0))
          )
        );
      }
      // テキスト情報
      var infoChildren = [
        h('div', { key: 't', className: 'uz-artCard__title' }, art.title),
        h('div', { key: 'm', className: 'uz-artCard__meta' },
          formatDate(art.date),
          art.productCount > 0 ? ' · ' + art.productCount + '冊' : ''
        ),
      ];
      if (art.categories && art.categories.length > 0) {
        infoChildren.push(
          h('div', { key: 'cats', className: 'uz-artCard__cats' },
            art.categories.map(function(c) { return h('span', { key: c, className: 'uz-artCard__cat' }, c); })
          )
        );
      }
      cardChildren.push(h('div', { key: 'info', className: 'uz-artCard__info' }, infoChildren));

      return h('a', {
        key: art.id, className: 'uz-artCard', href: '#',
        onClick: function(e) { e.preventDefault(); openArticleDetail(art); },
      }, cardChildren);
    });

    artPanelChildren.push(
      h('div', { key: 'grid', className: 'uz-artCardGrid' }, artCards)
    );
    children.push(h('div', { key: 'artPanel', className: 'uz-articlesPanel' }, artPanelChildren));
  }

  // 記事詳細ビュー
  if (showArticles && selectedArticle && uzData) {
    var articleBooks = getArticleBooks(selectedArticle.id);
    var detailChildren = [
      h('button', { key: 'back', className: 'uz-articleDetail__back', onClick: backToArticleList }, '← 記事一覧に戻る'),
      h('div', { key: 'hdr', className: 'uz-articleDetail__header' },
        selectedArticle.thumbnailUrl
          ? h('div', { className: 'uz-articleDetail__thumb' },
              h('img', { src: selectedArticle.thumbnailUrl, alt: '' })
            )
          : h('div', { className: 'uz-articleDetail__icon' }, shelfIcons[selectedArticle.shelf] || '\uD83D\uDCC4'),
        h('div', null,
          h('h2', { className: 'uz-articleDetail__title' }, selectedArticle.title),
          h('div', { className: 'uz-articleDetail__meta' },
            h('span', null, formatDate(selectedArticle.date)),
            selectedArticle.categories.map(function(c) { return h('span', { key: c, className: 'uz-articleCard__cat' }, c); })
          )
        )
      ),
    ];
    var actionChildren = [
      h('a', { key: 'read', href: selectedArticle.url, className: 'uz-articleDetail__readLink' }, '記事を読む →')
    ];
    if (articleBooks.length > 0) {
      actionChildren.push(h('button', { key: 'shelf', className: 'uz-articleDetail__shelfBtn', onClick: function() { showArticleBooksOnShelf(selectedArticle.id); } }, '本棚で表示'));
    }
    detailChildren.push(h('div', { key: 'actions', className: 'uz-articleDetail__actions' }, actionChildren));

    // 記事本文
    if (selectedArticle.body) {
      detailChildren.push(
        h('div', { key: 'body', className: 'uz-articleDetail__body', dangerouslySetInnerHTML: { __html: selectedArticle.body } })
      );
    }

    if (articleBooks.length > 0) {
      detailChildren.push(
        h('div', { key: 'books', className: 'uz-articleDetail__booksSection' },
          h('h3', { className: 'uz-articleDetail__booksTitle' }, 'この記事で紹介された本（' + articleBooks.length + '冊）'),
          h('div', { className: 'uz-articleDetail__miniShelf' },
            articleBooks.map(function(item, idx) { return renderBookItem(item, idx, 'ad'); })
          )
        )
      );
    } else {
      detailChildren.push(
        h('div', { key: 'empty', className: 'uz-emptyState' },
          h('div', { className: 'uz-emptyState__icon' }, '\uD83D\uDCDD'),
          h('div', { className: 'uz-emptyState__text' }, 'この記事に関連する本はまだ登録されていません')
        )
      );
    }

    // 本棚に戻るボタン
    detailChildren.push(
      h('div', { key: 'backToShelf', className: 'uz-backToShelf' },
        h('div', { className: 'uz-backToShelf__divider' }),
        h('button', {
          className: 'uz-backToShelf__btn',
          onClick: function() {
            setShowArticles(false);
            setSelectedArticle(null);
            setShelfIndex(0);
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
        },
          h('span', { className: 'uz-backToShelf__icon' }, '\uD83D\uDCDA'),
          h('span', { className: 'uz-backToShelf__text' }, '本棚に戻る')
        )
      )
    );

    children.push(h('div', { key: 'artDetail', className: 'uz-articleDetail' }, detailChildren));
  }

  // お気に入りパネル
  if (showFavorites) {
    children.push(
      h('div', { key: 'favPanel', className: 'uz-singleShelf' },
        h('section', { className: 'uz-shelf' },
          h('div', { className: 'uz-shelfHead' },
            h('h2', { className: 'uz-shelfTitle' }, h('span', { className: 'uz-shelfIcon' }, '\u2665'), ' お気に入り'),
            h('div', { className: 'uz-shelfMeta' }, (favoriteItems ? favoriteItems.length : 0) + ' items')
          ),
          favoriteItems && favoriteItems.length > 0
            ? h('div', { className: 'uz-rack' },
                h('div', { className: 'uz-plank', 'aria-hidden': 'true' }),
                h('div', { className: 'uz-mixedRow' }, favoriteItems.map(function(item, idx) { return renderBookItem(item, idx, 'fav'); }))
              )
            : h('div', { className: 'uz-emptyState' },
                h('div', { className: 'uz-emptyState__icon' }, '\u2665'),
                h('div', { className: 'uz-emptyState__text' }, 'お気に入りはまだありません'),
                h('div', { className: 'uz-emptyState__hint' }, '本をクリックして ♥ ボタンで追加できます')
              )
        )
      )
    );
  }

  // 検索結果
  if (!showArticles && !showFavorites && (searchResults || (mode === 'rakuten' && rakutenSearching && bookSearch.trim()))) {
    var searchContent;
    if (rakutenSearching && mode === 'rakuten') {
      searchContent = h('div', { className: 'uz-emptyState' },
        h('div', { className: 'uz-emptyState__icon' }, '\uD83D\uDD0D'),
        h('div', { className: 'uz-emptyState__text' }, '楽天ブックスを検索中...')
      );
    } else if (searchResults && searchResults.length > 0) {
      searchContent = h('div', { className: 'uz-rack' },
        h('div', { className: 'uz-plank', 'aria-hidden': 'true' }),
        h('div', { className: 'uz-mixedRow' }, searchResults.map(function(item, idx) { return renderBookItem(item, idx, 'sr'); }))
      );
    } else {
      searchContent = h('div', { className: 'uz-emptyState' },
        h('div', { className: 'uz-emptyState__icon' }, '\uD83D\uDD0D'),
        h('div', { className: 'uz-emptyState__text' }, '該当する本が見つかりませんでした')
      );
    }
    children.push(
      h('div', { key: 'searchRes', className: 'uz-singleShelf' },
        h('section', { className: 'uz-shelf' },
          h('div', { className: 'uz-shelfHead' },
            h('h2', { className: 'uz-shelfTitle' }, h('span', { className: 'uz-shelfIcon' }, '\uD83D\uDD0D'), ' 「' + bookSearch + '」の検索結果'),
            searchResults ? h('div', { className: 'uz-shelfMeta' }, searchResults.length + ' items') : null
          ),
          searchContent
        )
      )
    );
  }

  // 記事フィルタバー
  if (!showArticles && !showFavorites && filterByArticle && mode === 'uz') {
    children.push(
      h('div', { key: 'filterBar', className: 'uz-articleFilterBar' },
        h('span', { className: 'uz-articleFilterBar__label' }, '\uD83D\uDCDD 「' + truncate(filterArticleTitle, 30) + '」の本のみ表示中'),
        h('button', { className: 'uz-articleFilterBar__clear', onClick: function() { setFilterByArticle(null); } }, '\u2715 解除')
      )
    );
  }

  // メイン棚表示
  if (!showArticles && !showFavorites && !searchResults && currentShelf && !isLoading) {
    children.push(
      h('div', { key: 'shelf', className: 'uz-singleShelf' },
        h('section', { className: 'uz-shelf uz-shelf--' + currentShelf.id },
          h('div', { className: 'uz-shelfHead' },
            h('h2', { className: 'uz-shelfTitle' }, h('span', { className: 'uz-shelfIcon' }, shelfIcons[currentShelf.id] || ''), ' ' + currentShelf.title),
            h('div', { className: 'uz-shelfMeta' }, currentItems.length + ' items')
          ),
          h('div', { className: 'uz-rack' },
            h('div', { className: 'uz-plank', 'aria-hidden': 'true' }),
            h('div', { className: 'uz-mixedRow' },
              currentItems.map(function(item, idx) { return renderBookItem(item, idx, 'main'); })
            )
          )
        ),
        h('div', { className: 'uz-shelfPager' },
          h('button', { className: 'uz-shelfPager__btn', onClick: prevShelf, disabled: shelfIndex === 0 },
            '← ' + (shelfIndex > 0 ? allShelves[shelfIndex - 1].title : '')
          ),
          h('span', { className: 'uz-shelfPager__pos' }, (shelfIndex + 1) + ' / ' + allShelves.length),
          h('button', { className: 'uz-shelfPager__btn', onClick: nextShelf, disabled: shelfIndex === allShelves.length - 1 },
            (shelfIndex < allShelves.length - 1 ? allShelves[shelfIndex + 1].title : '') + ' →'
          )
        )
      )
    );
  }

  // ツールチップ
  if (tooltip) {
    var ttChildren = [
      h('div', { key: 't', className: 'uz-tooltip__title' }, tooltip.item.fullTitle || tooltip.item.title),
    ];
    if (tooltip.item.fullAuthor || tooltip.item.author) {
      ttChildren.push(h('div', { key: 'a', className: 'uz-tooltip__author' }, tooltip.item.fullAuthor || tooltip.item.author));
    }
    if (tooltip.item.comment) {
      ttChildren.push(h('div', { key: 'c', className: 'uz-tooltip__comment' }, tooltip.item.comment));
    }
    if (tooltip.item.tags && tooltip.item.tags.length > 0) {
      ttChildren.push(h('div', { key: 'tags', className: 'uz-tooltip__tags' },
        tooltip.item.tags.map(function(tag, i) { return h('span', { key: i, className: 'uz-tooltip__tag' }, tag); })
      ));
    }
    if (tooltip.item.articleTitle) {
      ttChildren.push(h('div', { key: 'art', className: 'uz-tooltip__article' }, truncate(tooltip.item.articleTitle, 40)));
    }
    ttChildren.push(h('div', { key: 'arrow', className: 'uz-tooltip__arrow' }));

    children.push(
      h('div', {
        key: 'tooltip', className: 'uz-tooltip',
        style: { position: 'fixed', left: Math.min(tooltip.x, window.innerWidth - 280), top: Math.max(8, tooltip.y - 8), transform: 'translate(-50%, -100%)', zIndex: 1000, pointerEvents: 'none' }
      }, ttChildren)
    );
  }

  // モーダル
  if (modal) {
    var modalCover = modal.coverUrl
      ? h('img', { src: modal.coverUrl, alt: modal.fullTitle || modal.title })
      : h('div', { className: 'uz-modal__noCover', style: { background: spineGradient(modal.fullTitle || modal.title) } },
          h('span', null, modal.fullTitle || modal.title)
        );

    var detailsChildren = [
      h('h2', { key: 'title', className: 'uz-modal__title' }, modal.fullTitle || modal.title),
      h('p', { key: 'author', className: 'uz-modal__author' }, modal.fullAuthor || modal.author),
    ];
    if (modal.publisher) detailsChildren.push(h('p', { key: 'pub', className: 'uz-modal__meta' }, '出版社: ' + modal.publisher));
    if (modal.salesDate) detailsChildren.push(h('p', { key: 'date', className: 'uz-modal__meta' }, '発売日: ' + modal.salesDate));
    if (modal.format && FORMAT_SIZES[modal.format] && FORMAT_SIZES[modal.format].label) {
      detailsChildren.push(h('p', { key: 'fmt', className: 'uz-modal__meta' }, '判型: ' + FORMAT_SIZES[modal.format].label));
    }
    if (modal.price) detailsChildren.push(h('p', { key: 'price', className: 'uz-modal__price' }, '¥' + Number(modal.price).toLocaleString()));
    if (modal.reviewAverage && modal.reviewAverage !== '0') {
      detailsChildren.push(h('div', { key: 'review', className: 'uz-modal__review' },
        h('span', { className: 'uz-modal__stars' }, renderStars(modal.reviewAverage)),
        h('span', null, ' ' + modal.reviewAverage + ' / 5.0 (' + modal.reviewCount + '件のレビュー)')
      ));
    }
    if (modal.caption) detailsChildren.push(h('p', { key: 'cap', className: 'uz-modal__caption' }, truncate(modal.caption, 300)));

    // 関連記事セクション
    if (modal.articleTitle) {
      var relatedBooks = getArticleBooks(modal.articleId).filter(function(b) { return (b.id || b.fullTitle) !== (modal.id || modal.fullTitle); });
      var artSectionChildren = [
        h('div', { key: 'link', className: 'uz-modal__articleLink' },
          h('span', { className: 'uz-modal__articleLabel' }, '関連記事'),
          h('div', { className: 'uz-modal__articleLinkRow' },
            h('a', { href: modal.articleUrl || ('/entry/' + modal.articleId) }, modal.articleTitle),
            h('button', { className: 'uz-modal__articleDetailBtn', onClick: function() { openArticleFromModal(modal.articleId); } }, '詳細を見る')
          )
        )
      ];
      if (relatedBooks.length > 0) {
        artSectionChildren.push(
          h('div', { key: 'related', className: 'uz-modal__relatedBooks' },
            h('span', { className: 'uz-modal__relatedLabel' }, 'この記事の他の本（' + relatedBooks.length + '冊）'),
            h('div', { className: 'uz-modal__relatedList' },
              relatedBooks.slice(0, 8).map(function(b, i) {
                return h('button', {
                  key: 'rel-' + i, className: 'uz-modal__relatedThumb',
                  onClick: function() { setModal(b); }, title: b.fullTitle || b.title
                },
                  b.coverUrl
                    ? h('img', { src: b.coverUrl, alt: '' })
                    : h('div', { className: 'uz-modal__relatedPlaceholder', style: { background: spineGradient(b.fullTitle || b.title) } },
                        h('span', null, truncate(b.title, 6))
                      )
                );
              })
            )
          )
        );
      }
      detailsChildren.push(h('div', { key: 'artSection', className: 'uz-modal__articleSection' }, artSectionChildren));
    }

    // アクションボタン
    var actionBtns = [
      h('button', {
        key: 'fav', className: 'uz-modal__btn uz-modal__btn--fav' + (isFavorite(modal) ? ' active' : ''),
        onClick: function() { toggleFavorite(modal); }
      }, isFavorite(modal) ? '\u2665 お気に入り済み' : '\u2661 お気に入りに追加')
    ];
    if (modal.affiliateUrl || modal.url) {
      actionBtns.push(h('a', { key: 'rak', href: modal.affiliateUrl || modal.url, target: '_blank', rel: 'noopener noreferrer', className: 'uz-modal__btn uz-modal__btn--rakuten' }, '楽天で購入'));
    }
    if (modal.amazonUrl) {
      actionBtns.push(h('a', { key: 'amz', href: modal.amazonUrl, target: '_blank', rel: 'noopener noreferrer', className: 'uz-modal__btn uz-modal__btn--amazon' }, 'Amazonで見る'));
    }
    if (modal.rakutenUrl && !modal.affiliateUrl) {
      actionBtns.push(h('a', { key: 'rak2', href: modal.rakutenUrl, target: '_blank', rel: 'noopener noreferrer', className: 'uz-modal__btn uz-modal__btn--rakuten' }, '楽天で見る'));
    }
    detailsChildren.push(h('div', { key: 'actions', className: 'uz-modal__actions' }, actionBtns));

    children.push(
      h('div', { key: 'modal', className: 'uz-modalOverlay', onClick: closeModal, role: 'dialog', 'aria-label': '書籍詳細' },
        h('div', { className: 'uz-modal', onClick: function(e) { e.stopPropagation(); } },
          h('button', { className: 'uz-modal__close', onClick: closeModal }, '\u2715'),
          h('div', { className: 'uz-modal__inner' },
            h('div', { className: 'uz-modal__cover' }, modalCover),
            h('div', { className: 'uz-modal__details' }, detailsChildren)
          )
        )
      )
    );
  }

  return h('div', { className: 'uz-wrap' }, children);
}
