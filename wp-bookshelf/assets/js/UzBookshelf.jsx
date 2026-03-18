// ============================================================
// UZ Bookshelf — WordPress Plugin版 v1.0
// WP REST API対応 + 記事詳細ビュー, モーダル内関連本,
// 記事カテゴリフィルタ, 持続フィルタ, キーボード拡充
// ============================================================

// WordPress config (injected by wp_localize_script)
const _uzConfig = typeof uzBookshelfConfig !== 'undefined' ? uzBookshelfConfig : {
  apiBase: '',
  nonce: '',
  coversBase: 'covers/',
};

// Helper for API fetch with nonce
function uzFetch(endpoint) {
  const url = _uzConfig.apiBase ? _uzConfig.apiBase + endpoint : endpoint;
  const opts = {};
  if (_uzConfig.nonce) {
    opts.headers = { 'X-WP-Nonce': _uzConfig.nonce };
  }
  return fetch(url, opts).then(r => r.json());
}

// ---- ユーティリティ ----
function stableHash(str) {
  let h = 2166136261;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return Math.abs(h);
}

function spineColorFromTitle(title) {
  const h = stableHash(title) % 360;
  const s = 25 + (stableHash(title + 'sat') % 20);
  const l = 32 + (stableHash(title + 'lit') % 18);
  return { h, s, l, css: `hsl(${h} ${s}% ${l}%)` };
}

function spineGradient(title) {
  const c = spineColorFromTitle(title);
  const light = `hsl(${c.h} ${c.s}% ${c.l + 12}%)`;
  const dark = `hsl(${c.h} ${c.s}% ${c.l - 8}%)`;
  return `linear-gradient(135deg, ${light} 0%, ${c.css} 50%, ${dark} 100%)`;
}

function shuffleArray(array) {
  const shuffled = [...array];
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  return shuffled;
}

function truncate(str, len) {
  if (!str) return '';
  return str.length > len ? str.substring(0, len) + '…' : str;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const m = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/);
  if (m) return `${m[3]}.${m[1]}.${m[2]}`;
  return dateStr;
}

function renderStars(rating) {
  if (!rating || rating === '0') return null;
  const num = parseFloat(rating);
  const full = Math.floor(num);
  const half = num - full >= 0.5;
  const stars = [];
  for (let i = 0; i < full; i++) stars.push('★');
  if (half) stars.push('☆');
  return stars.join('');
}

// ---- 判型サイズマッピング ----
const FORMAT_SIZES = {
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
  const fmt = item.format || detectFormat(item.fullTitle || item.title);
  const base = FORMAT_SIZES[fmt] || FORMAT_SIZES.standard;
  const scale = 1.0;
  return {
    width: Math.round(base.w * scale),
    height: Math.round(base.h * scale),
    format: fmt,
    label: base.label,
    thickness: (fmt === 'disc' || fmt === 'poster') ? 6 : (fmt === 'bunko' ? 14 : 18 + (stableHash(item.title || '') % 12)),
  };
}

// ---- お気に入りヘルパー ----
function loadFavorites() {
  try {
    return JSON.parse(localStorage.getItem('uz-favorites') || '[]');
  } catch { return []; }
}

function saveFavorites(favs) {
  localStorage.setItem('uz-favorites', JSON.stringify(favs));
}

function favKey(item) {
  return item.isbn || item.id || (item.source + '_' + (item.fullTitle || item.title));
}

// ============================================================
// 表紙コンポーネント — 美術的3D（親の外に定義して再マウント防止）
// ============================================================
const BookFace = React.memo(({ item, isHighlighted, isFav, onClick, onMouseEnter, onMouseLeave }) => {
  const dim = getBookDimensions(item);
  const [imgLoaded, setImgLoaded] = React.useState(false);
  const isDisc = dim.format === 'disc';
  const isPoster = dim.format === 'poster';

  return (
    <a
      className={`uz-book ${isHighlighted ? 'uz-highlight' : ''} ${isDisc ? 'uz-book--disc' : ''} ${isPoster ? 'uz-book--poster' : ''}`}
      href="#" onClick={onClick} onMouseEnter={onMouseEnter} onMouseLeave={onMouseLeave}
      style={{ width: dim.width, height: dim.height }}
    >
      <div className="uz-book__body">
        <div className="uz-book__front">
          {item.coverUrl && (
            <img
              className={`uz-book__img ${imgLoaded ? 'loaded' : ''}`}
              src={item.coverUrl} alt=""
              onLoad={() => setImgLoaded(true)}
              onError={(e) => { e.target.style.display = 'none'; }}
            />
          )}
          {(!item.coverUrl || !imgLoaded) && (
            <div className="uz-book__placeholder" style={{ background: spineGradient(item.fullTitle || item.title) }}>
              <span className="uz-book__placeholderText">{truncate(item.fullTitle || item.title, 20)}</span>
              <span className="uz-book__placeholderAuthor">{item.author || item.fullAuthor || ''}</span>
            </div>
          )}
          <div className="uz-book__gloss" />
        </div>
        <div className="uz-book__spine" style={{ background: spineGradient(item.fullTitle || item.title), width: dim.thickness }} />
        {!isDisc && !isPoster && <div className="uz-book__pages" style={{ height: dim.thickness }} />}
        {item.reviewAverage && item.reviewAverage !== '0' && (
          <div className="uz-book__badge">{renderStars(item.reviewAverage)}</div>
        )}
        {isFav && <div className="uz-book__favBadge">♥</div>}
      </div>
      <div className="uz-book__shadow" />
    </a>
  );
});

// ============================================================
// 背表紙コンポーネント — リアルな質感（親の外に定義して再マウント防止）
// ============================================================

// 背表紙の装飾パターンを生成（本ごとにユニーク）
function spineDecor(title) {
  const h = stableHash(title);
  const variant = h % 5; // 5種類のデザインバリエーション
  const c = spineColorFromTitle(title);
  const accent = `hsl(${(c.h + 30) % 360} ${c.s + 10}% ${c.l + 20}%)`;
  const gold = `hsl(${40 + (h % 20)} ${50 + (h % 20)}% ${55 + (h % 15)}%)`;
  return { variant, accent, gold };
}

const BookSpine = React.memo(({ item, isHighlighted, onClick, onMouseEnter, onMouseLeave }) => {
  const dim = getBookDimensions(item);
  const isDisc = dim.format === 'disc' || dim.format === 'poster';
  const thickness = isDisc ? 8 : dim.thickness;
  const decor = spineDecor(item.fullTitle || item.title);
  const title = item.fullTitle || item.title || '';
  const author = item.fullAuthor || item.author || '';

  // Determine max chars based on spine height
  const maxTitleChars = Math.floor(dim.height / 10);
  const maxAuthorChars = Math.floor(dim.height / 16);

  return (
    <a
      className={`uz-spine2 uz-spine2--v${decor.variant} ${isHighlighted ? 'uz-highlight' : ''} ${isDisc ? 'uz-spine2--disc' : ''}`}
      href="#" onClick={onClick} onMouseEnter={onMouseEnter} onMouseLeave={onMouseLeave}
      style={{ width: thickness, height: dim.height }}
    >
      <div className="uz-spine2__body" style={{ background: spineGradient(title) }}>
        <div className="uz-spine2__texture" />
        {/* Top band */}
        <div className="uz-spine2__bandTop" style={{ background: decor.gold }} />
        <div className="uz-spine2__text">
          <span className="uz-spine2__title" style={{ color: decor.gold }}>{truncate(title, maxTitleChars)}</span>
          {author && (
            <span className="uz-spine2__author">{truncate(author, maxAuthorChars)}</span>
          )}
        </div>
        {/* Bottom band */}
        <div className="uz-spine2__bandBottom" style={{ background: decor.gold }} />
        {/* Publisher mark */}
        <div className="uz-spine2__pub" style={{ borderColor: decor.gold }}>
          <span style={{ color: decor.gold }}>{(author || title).charAt(0)}</span>
        </div>
        <div className="uz-spine2__edge" />
        <div className="uz-spine2__gloss" />
      </div>
    </a>
  );
});

// ============================================================
// メインコンポーネント
// ============================================================
function UzBookshelf() {
  const [mode, setMode] = React.useState('uz');
  const [rakutenData, setRakutenData] = React.useState(null);
  const [uzData, setUzData] = React.useState(null);
  const [genreMap, setGenreMap] = React.useState({});
  const [modal, setModal] = React.useState(null);
  const [tooltip, setTooltip] = React.useState(null);
  const [activeShelf, setActiveShelf] = React.useState(null);
  const [showArticles, setShowArticles] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState('');
  // highlightArticle は filterByArticle (A4) に置換済み
  const [shelfIndex, setShelfIndex] = React.useState(0);
  const tooltipTimeoutRef = React.useRef(null);

  // --- 新機能: 検索、ジャンルフィルタ、ローディング、お気に入り ---
  const [bookSearch, setBookSearch] = React.useState('');
  const [selectedGenre, setSelectedGenre] = React.useState(null);
  const [loadError, setLoadError] = React.useState(null);
  const [favorites, setFavorites] = React.useState(loadFavorites);
  const [showFavorites, setShowFavorites] = React.useState(false);

  // --- A1: 記事詳細ビュー ---
  const [selectedArticle, setSelectedArticle] = React.useState(null);

  // --- A3: 記事カテゴリフィルタ ---
  const [activeCategory, setActiveCategory] = React.useState(null);

  // --- A4: 持続フィルタ（記事→棚のフィルタ） ---
  const [filterByArticle, setFilterByArticle] = React.useState(null);

  // お気に入り永続化
  React.useEffect(() => { saveFavorites(favorites); }, [favorites]);

  const toggleFavorite = (item) => {
    const key = favKey(item);
    setFavorites(prev => {
      if (prev.includes(key)) return prev.filter(k => k !== key);
      return [...prev, key];
    });
  };

  const isFavorite = (item) => favorites.includes(favKey(item));

  const handleMouseEnter = (e, item) => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    tooltipTimeoutRef.current = setTimeout(() => {
      // 本の要素の位置を取得してツールチップを本の上に重ねる
      const el = e.currentTarget;
      const rect = el.getBoundingClientRect();
      setTooltip({
        x: rect.left + rect.width / 2,
        y: rect.top,
        item
      });
    }, 350);
  };
  const handleMouseLeave = () => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    setTooltip(null);
  };

  // --- 楽天データ (WP REST API) ---
  React.useEffect(() => {
    // Genre map: in WordPress version, genre info comes with the rakuten data
    // We set a minimal map and it will be enriched from the actual data
    setGenreMap({ '001005': 'テクノロジー', '001006': 'ビジネス', '001010': 'カルチャー' });
  }, []);

  React.useEffect(() => {
    if (Object.keys(genreMap).length === 0) return;
    Promise.all([
      uzFetch('/rakuten/001005'),
      uzFetch('/rakuten/001006'),
      uzFetch('/rakuten/001010'),
    ]).then(([j5, j6, j10]) => {
      const grouped = { tech: [], biz: [], culture: [] };
      [{ json: j5, shelf: 'tech' }, { json: j6, shelf: 'biz' }, { json: j10, shelf: 'culture' }]
        .forEach(({ json, shelf }) => {
          (json.Items || []).forEach(item => {
            const b = item.Item;
            const bookData = {
              id: b.isbn, title: truncate(b.title, 20), fullTitle: b.title,
              author: truncate(b.author, 16), fullAuthor: b.author,
              coverUrl: b.largeImageUrl, url: b.itemUrl,
              affiliateUrl: b.affiliateUrl || '', isbn: b.isbn,
              price: b.itemPrice, reviewAverage: b.reviewAverage,
              reviewCount: b.reviewCount, caption: b.itemCaption || '',
              publisher: b.publisherName || '', salesDate: b.salesDate || '',
              genreId: b.booksGenreId || '', shelf, source: 'rakuten',
              format: detectFormat(b.title),
            };
            grouped[shelf].push(bookData);
          });
        });

      setRakutenData([
        { id: 'tech', title: 'テクノロジー', books: grouped.tech },
        { id: 'biz', title: 'ビジネス', books: grouped.biz },
        { id: 'culture', title: 'カルチャー', books: grouped.culture },
      ]);
    }).catch(e => { console.error('Book load error:', e); setLoadError('書籍データの読み込みに失敗しました'); });
  }, [genreMap]);

  // --- uzデータ (WP REST API) ---
  React.useEffect(() => {
    uzFetch('/shelves').then(data => setUzData(data))
      .catch(e => { console.error('uz data:', e); setLoadError('本棚データの読み込みに失敗しました'); });
  }, []);

  // --- 棚データ構築 ---
  const rakutenShelves = React.useMemo(() => {
    if (!rakutenData) return [];
    return rakutenData.map(s => {
      const books = s.books.slice(0, 30).map(b => ({
        ...b,
        type: b.type || (b.coverUrl ? 'featured' : 'spine'),
      }));
      return { ...s, mixedBooks: books };
    });
  }, [rakutenData]);

  const uzShelves = React.useMemo(() => {
    if (!uzData) return [];
    return uzData.shelves.filter(s => s.items.length > 0).map(s => {
      const isFilm = s.id === 'film';
      const items = s.items.map((item) => {
        // Respect the type field from DB: 'spine' stays spine even with cover
        // 'featured' or 'product' with cover → face display
        // No cover → always spine
        let displayType = item.type || 'product';
        if (displayType === 'spine') {
          displayType = 'spine';
        } else {
          displayType = item.coverUrl ? 'featured' : 'spine';
        }
        return {
          ...item,
          type: displayType,
          source: 'uz',
          shelfId: s.id,
          format: isFilm ? 'poster' : (item.format || detectFormat(item.fullTitle || item.title)),
        };
      });
      return { ...s, mixedItems: items };
    });
  }, [uzData]);

  const filteredArticles = React.useMemo(() => {
    if (!uzData) return [];
    let articles = uzData.articles;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      articles = articles.filter(a => a.title.toLowerCase().includes(q) || a.categories.some(c => c.toLowerCase().includes(q)));
    }
    if (activeShelf && activeShelf !== 'all') articles = articles.filter(a => a.shelf === activeShelf);
    // A3: カテゴリフィルタ
    if (activeCategory) articles = articles.filter(a => a.categories && a.categories.includes(activeCategory));
    return articles;
  }, [uzData, searchQuery, activeShelf, activeCategory]);

  // A3: 全記事のカテゴリ一覧
  const allCategories = React.useMemo(() => {
    if (!uzData) return [];
    const cats = new Set();
    uzData.articles.forEach(a => {
      (a.categories || []).forEach(c => { if (c) cats.add(c); });
    });
    return [...cats].sort();
  }, [uzData]);

  // --- 棚ナビゲーション ---
  const allShelves = React.useMemo(() => {
    if (mode === 'uz') return uzShelves;
    return rakutenShelves;
  }, [mode, uzShelves, rakutenShelves]);

  const currentShelf = allShelves[shelfIndex] || null;

  const goToShelf = (idx) => {
    setShelfIndex(idx);
    setSelectedGenre(null);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const prevShelf = () => goToShelf(Math.max(0, shelfIndex - 1));
  const nextShelf = () => goToShelf(Math.min(allShelves.length - 1, shelfIndex + 1));

  // モード切替時にリセット
  const switchMode = (newMode) => {
    setMode(newMode);
    setShelfIndex(0);
    setShowArticles(false);
    setShowFavorites(false);
    setBookSearch('');
    setSelectedGenre(null);
    setSelectedArticle(null);
    setFilterByArticle(null);
  };

  const openModal = (item, e) => { if (e) e.preventDefault(); setModal(item); };
  const closeModal = () => setModal(null);

  // A1: 記事カードクリック → 記事詳細ビューを表示
  const openArticleDetail = (article) => {
    setSelectedArticle(article);
  };

  // A1: 記事詳細から一覧に戻る
  const backToArticleList = () => {
    setSelectedArticle(null);
  };

  // A4: 記事詳細から棚にフィルタ付きジャンプ
  const showArticleBooksOnShelf = (articleId) => {
    setShowArticles(false);
    setSelectedArticle(null);
    setFilterByArticle(articleId);
  };

  // A2: モーダル内から記事詳細ビューを開く
  const openArticleFromModal = (articleId) => {
    if (!uzData) return;
    const art = uzData.articles.find(a => a.id === articleId);
    if (art) {
      closeModal();
      setShowArticles(true);
      setShowFavorites(false);
      setSelectedArticle(art);
    }
  };

  // A1: 記事に関連する本を取得
  const getArticleBooks = React.useCallback((articleId) => {
    if (!uzData) return [];
    const books = [];
    uzData.shelves.forEach(s => {
      s.items.forEach(item => {
        if (item.articleId === articleId) {
          let displayType = item.type || 'product';
          if (displayType !== 'spine') {
            displayType = item.coverUrl ? 'featured' : 'spine';
          }
          books.push({
            ...item,
            type: displayType,
            source: 'uz',
            shelfId: s.id,
            format: s.id === 'film' ? 'poster' : (item.format || detectFormat(item.fullTitle || item.title)),
          });
        }
      });
    });
    return books;
  }, [uzData]);

  const shelfIcons = { books: '📚', manga: '📖', film: '🎬', music: '🎵', tech: '💻', biz: '💼', culture: '🌍' };

  // --- キーボードナビゲーション ---
  React.useEffect(() => {
    const handler = (e) => {
      if (e.key === 'Escape') {
        if (modal) { closeModal(); return; }
        if (selectedArticle) { backToArticleList(); return; }
        if (filterByArticle) { setFilterByArticle(null); return; }
      }
      // 検索入力中はキーボードナビ無効
      if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) return;
      if (e.key === 'ArrowLeft' && !showArticles && !showFavorites) { prevShelf(); e.preventDefault(); }
      if (e.key === 'ArrowRight' && !showArticles && !showFavorites) { nextShelf(); e.preventDefault(); }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [modal, selectedArticle, filterByArticle, shelfIndex, allShelves.length, showArticles, showFavorites]);

  // --- ジャンルフィルタ用: 現在の棚のジャンル一覧 (A2) ---
  const currentGenres = React.useMemo(() => {
    if (mode !== 'rakuten' || !currentShelf) return [];
    const books = currentShelf.mixedBooks || [];
    const ids = new Set();
    books.forEach(b => { if (b.genreId) ids.add(b.genreId); });
    return [...ids].map(id => ({ id, name: genreMap[id] || id })).sort((a, b) => a.name.localeCompare(b.name));
  }, [mode, currentShelf, genreMap]);

  // --- 棚の本リスト（UZ/楽天で統一） ---
  const currentItems = React.useMemo(() => {
    if (!currentShelf) return [];
    let items = currentShelf.mixedItems || currentShelf.mixedBooks || [];

    // ジャンルフィルタ
    if (selectedGenre && mode === 'rakuten') {
      items = items.filter(b => b.genreId === selectedGenre);
    }

    // A4: 記事フィルタ
    if (filterByArticle && mode === 'uz') {
      items = items.filter(b => b.articleId === filterByArticle);
    }

    return items;
  }, [currentShelf, selectedGenre, mode, filterByArticle]);

  // A4: フィルタ中の記事タイトルを取得
  const filterArticleTitle = React.useMemo(() => {
    if (!filterByArticle || !uzData) return '';
    const art = uzData.articles.find(a => a.id === filterByArticle);
    return art ? art.title : filterByArticle;
  }, [filterByArticle, uzData]);

  // --- 検索結果 (A1): 全棚横断検索 ---
  const searchResults = React.useMemo(() => {
    if (!bookSearch.trim()) return null;
    const q = bookSearch.toLowerCase();
    const allItems = [];

    if (mode === 'uz') {
      uzShelves.forEach(s => {
        (s.mixedItems || []).forEach(item => {
          if (
            (item.fullTitle || item.title || '').toLowerCase().includes(q) ||
            (item.fullAuthor || item.author || '').toLowerCase().includes(q)
          ) allItems.push(item);
        });
      });
    } else {
      rakutenShelves.forEach(s => {
        (s.mixedBooks || []).forEach(item => {
          if (
            (item.fullTitle || item.title || '').toLowerCase().includes(q) ||
            (item.fullAuthor || item.author || '').toLowerCase().includes(q)
          ) allItems.push(item);
        });
      });
    }

    return allItems;
  }, [bookSearch, mode, uzShelves, rakutenShelves]);

  // --- お気に入りリスト (A6) ---
  const favoriteItems = React.useMemo(() => {
    if (!showFavorites) return null;
    const all = [];

    uzShelves.forEach(s => {
      (s.mixedItems || []).forEach(item => {
        if (isFavorite(item)) all.push(item);
      });
    });
    rakutenShelves.forEach(s => {
      (s.mixedBooks || []).forEach(item => {
        if (isFavorite(item)) all.push(item);
      });
    });

    return all;
  }, [showFavorites, favorites, uzShelves, rakutenShelves]);

  // --- ローディング判定 (A4) ---
  const isLoading = !uzData && !loadError;

  // ============================================================
  // RENDER
  // ============================================================
  return (
    <div className="uz-wrap">
      <header className="uz-header">
        <div>
          <div className="uz-kicker">UZ MEDIA</div>
          <h1 className="uz-title">UZ Bookshelf</h1>
        </div>
        <div className="uz-headerActions">
          <button className={`uz-tabBtn ${mode === 'uz' && !showArticles && !showFavorites ? 'active' : ''}`} onClick={() => { switchMode('uz'); }}>UZ セレクション</button>
          <button className={`uz-tabBtn ${mode === 'rakuten' && !showArticles && !showFavorites ? 'active' : ''}`} onClick={() => { switchMode('rakuten'); }}>楽天Books</button>
          {mode === 'uz' && (
            <button className={`uz-tabBtn ${showArticles ? 'active' : ''}`} onClick={() => { setShowArticles(!showArticles); setShowFavorites(false); }}>記事一覧</button>
          )}
          <button
            className={`uz-tabBtn ${showFavorites ? 'active' : ''}`}
            onClick={() => { setShowFavorites(!showFavorites); setShowArticles(false); }}
          >
            ♥ お気に入り{favorites.length > 0 && <span className="uz-favCount">{favorites.length}</span>}
          </button>
        </div>
      </header>

      {/* 検索バー (A1) — 記事/お気に入り以外で表示 */}
      {!showArticles && !showFavorites && (
        <div className="uz-bookSearchBar">
          <input
            className="uz-bookSearchInput"
            type="text"
            placeholder={mode === 'uz' ? 'タイトル・著者で検索...' : '楽天書籍を検索...'}
            value={bookSearch}
            onChange={e => setBookSearch(e.target.value)}
          />
          {bookSearch && (
            <button className="uz-bookSearchClear" onClick={() => setBookSearch('')}>✕</button>
          )}
        </div>
      )}

      {/* 棚セレクター — 検索中は非表示 */}
      {!showArticles && !showFavorites && !searchResults && allShelves.length > 0 && (
        <nav className="uz-shelfNav">
          {allShelves.map((s, i) => (
            <button
              key={s.id}
              className={`uz-shelfNav__btn ${i === shelfIndex ? 'active' : ''}`}
              onClick={() => goToShelf(i)}
            >
              <span className="uz-shelfNav__icon">{shelfIcons[s.id] || ''}</span>
              <span className="uz-shelfNav__label">{s.title}</span>
            </button>
          ))}
        </nav>
      )}

      {/* ジャンルフィルタ (A2) — 楽天モード・棚表示中 */}
      {!showArticles && !showFavorites && !searchResults && mode === 'rakuten' && currentGenres.length > 1 && (
        <div className="uz-genreFilter">
          <button
            className={`uz-genreBtn ${!selectedGenre ? 'active' : ''}`}
            onClick={() => setSelectedGenre(null)}
          >すべて</button>
          {currentGenres.map(g => (
            <button
              key={g.id}
              className={`uz-genreBtn ${selectedGenre === g.id ? 'active' : ''}`}
              onClick={() => setSelectedGenre(g.id)}
            >{g.name}</button>
          ))}
        </div>
      )}

      {/* ローディング状態 (A4) */}
      {isLoading && (
        <div className="uz-loading">
          <div className="uz-loading__spinner" />
          <div className="uz-loading__text">本棚を読み込み中...</div>
        </div>
      )}

      {/* エラー状態 (A4) */}
      {loadError && (
        <div className="uz-error">
          <div className="uz-error__icon">!</div>
          <div className="uz-error__text">{loadError}</div>
          <button className="uz-error__retry" onClick={() => { setLoadError(null); location.reload(); }}>再読み込み</button>
        </div>
      )}

      {/* 記事一覧パネル (A1改修: 記事詳細ビュー対応) */}
      {showArticles && uzData && !selectedArticle && (
        <div className="uz-articlesPanel">
          <div className="uz-articlesPanelHead">
            <h2>UZ 記事一覧</h2>
            <input className="uz-searchInput" type="text" placeholder="記事を検索..." value={searchQuery} onChange={e => setSearchQuery(e.target.value)} />
            <div className="uz-shelfFilter">
              <button className={`uz-filterBtn ${!activeShelf || activeShelf === 'all' ? 'active' : ''}`} onClick={() => setActiveShelf('all')}>すべて</button>
              {uzData.shelves.map(s => (
                <button key={s.id} className={`uz-filterBtn ${activeShelf === s.id ? 'active' : ''}`} onClick={() => setActiveShelf(s.id)}>{s.title}</button>
              ))}
            </div>
            {/* A3: カテゴリフィルタ */}
            {allCategories.length > 0 && (
              <div className="uz-categoryFilter">
                <button className={`uz-filterBtn ${!activeCategory ? 'active' : ''}`} onClick={() => setActiveCategory(null)}>全カテゴリ</button>
                {allCategories.map(c => (
                  <button key={c} className={`uz-filterBtn ${activeCategory === c ? 'active' : ''}`} onClick={() => setActiveCategory(c)}>{c}</button>
                ))}
              </div>
            )}
          </div>
          <div className="uz-articlesList">
            {filteredArticles.map(art => (
              <div key={art.id} className="uz-articleCard" onClick={() => openArticleDetail(art)}>
                <div className="uz-articleCard__icon">{shelfIcons[art.shelf] || '📄'}</div>
                <div className="uz-articleCard__body">
                  <div className="uz-articleCard__title">{art.title}</div>
                  <div className="uz-articleCard__meta">
                    <span>{formatDate(art.date)}</span>
                    {art.categories.map(c => <span key={c} className="uz-articleCard__cat">{c}</span>)}
                    <span className="uz-articleCard__count">{art.productCount}点</span>
                  </div>
                </div>
                <a href={art.url} target="_blank" rel="noopener noreferrer" className="uz-articleCard__link" onClick={e => e.stopPropagation()}>記事を読む →</a>
              </div>
            ))}
          </div>
          {/* 本棚に戻るボタン（記事一覧） */}
          <div className="uz-backToShelf">
            <div className="uz-backToShelf__divider" />
            <button className="uz-backToShelf__btn" onClick={() => { setShowArticles(false); setSelectedArticle(null); setShelfIndex(0); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>
              <span className="uz-backToShelf__icon">📚</span>
              <span className="uz-backToShelf__text">本棚に戻る</span>
            </button>
          </div>
        </div>
      )}

      {/* A1: 記事詳細ビュー */}
      {showArticles && selectedArticle && uzData && (() => {
        const articleBooks = getArticleBooks(selectedArticle.id);
        return (
          <div className="uz-articleDetail">
            <button className="uz-articleDetail__back" onClick={backToArticleList}>← 記事一覧に戻る</button>
            <div className="uz-articleDetail__header">
              <div className="uz-articleDetail__icon">{shelfIcons[selectedArticle.shelf] || '📄'}</div>
              <div>
                <h2 className="uz-articleDetail__title">{selectedArticle.title}</h2>
                <div className="uz-articleDetail__meta">
                  <span>{formatDate(selectedArticle.date)}</span>
                  {selectedArticle.categories.map(c => <span key={c} className="uz-articleCard__cat">{c}</span>)}
                </div>
              </div>
            </div>
            <div className="uz-articleDetail__actions">
              <a href={selectedArticle.url} target="_blank" rel="noopener noreferrer" className="uz-articleDetail__readLink">記事を読む →</a>
              {articleBooks.length > 0 && (
                <button className="uz-articleDetail__shelfBtn" onClick={() => showArticleBooksOnShelf(selectedArticle.id)}>
                  本棚で表示
                </button>
              )}
            </div>

            {/* 記事本文 */}
            {selectedArticle.body && (
              <div className="uz-articleDetail__body" dangerouslySetInnerHTML={{ __html: selectedArticle.body }} />
            )}

            {articleBooks.length > 0 ? (
              <div className="uz-articleDetail__booksSection">
                <h3 className="uz-articleDetail__booksTitle">この記事で紹介された本（{articleBooks.length}冊）</h3>
                <div className="uz-articleDetail__miniShelf">
                  {articleBooks.map((item, idx) => {
                    const handlers = {
                      onClick: e => openModal(item, e),
                      onMouseEnter: e => handleMouseEnter(e, item),
                      onMouseLeave: handleMouseLeave,
                    };
                    if (item.type === 'featured') {
                      return <BookFace key={`ad-${item.id}-${idx}`} item={item} isHighlighted={false} isFav={isFavorite(item)} {...handlers} />;
                    }
                    return <BookSpine key={`ad-${item.id}-${idx}`} item={item} isHighlighted={false} {...handlers} />;
                  })}
                </div>
              </div>
            ) : (
              <div className="uz-emptyState">
                <div className="uz-emptyState__icon">📝</div>
                <div className="uz-emptyState__text">この記事に関連する本はまだ登録されていません</div>
              </div>
            )}

            {/* 本棚に戻るボタン */}
            <div className="uz-backToShelf">
              <div className="uz-backToShelf__divider" />
              <button className="uz-backToShelf__btn" onClick={() => { setShowArticles(false); setSelectedArticle(null); setShelfIndex(0); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>
                <span className="uz-backToShelf__icon">📚</span>
                <span className="uz-backToShelf__text">本棚に戻る</span>
              </button>
            </div>
          </div>
        );
      })()}

      {/* お気に入りパネル (A6) */}
      {showFavorites && (
        <div className="uz-singleShelf">
          <section className="uz-shelf">
            <div className="uz-shelfHead">
              <h2 className="uz-shelfTitle">
                <span className="uz-shelfIcon">♥</span>
                お気に入り
              </h2>
              <div className="uz-shelfMeta">{favoriteItems ? favoriteItems.length : 0} items</div>
            </div>
            {favoriteItems && favoriteItems.length > 0 ? (
              <div className="uz-rack">
                <div className="uz-plank" aria-hidden="true" />
                <div className="uz-mixedRow">
                  {favoriteItems.map((item, idx) => {
                    const handlers = {
                      onClick: e => openModal(item, e),
                      onMouseEnter: e => handleMouseEnter(e, item),
                      onMouseLeave: handleMouseLeave,
                    };
                    if (item.type === 'featured') {
                      return <BookFace key={`fav-${favKey(item)}-${idx}`} item={item} isHighlighted={false} isFav={isFavorite(item)} {...handlers} />;
                    }
                    return <BookSpine key={`fav-${favKey(item)}-${idx}`} item={item} isHighlighted={false} {...handlers} />;
                  })}
                </div>
              </div>
            ) : (
              <div className="uz-emptyState">
                <div className="uz-emptyState__icon">♥</div>
                <div className="uz-emptyState__text">お気に入りはまだありません</div>
                <div className="uz-emptyState__hint">本をクリックして ♥ ボタンで追加できます</div>
              </div>
            )}
          </section>
        </div>
      )}

      {/* === 検索結果表示 (A1) === */}
      {!showArticles && !showFavorites && searchResults && (
        <div className="uz-singleShelf">
          <section className="uz-shelf">
            <div className="uz-shelfHead">
              <h2 className="uz-shelfTitle">
                <span className="uz-shelfIcon">🔍</span>
                「{bookSearch}」の検索結果
              </h2>
              <div className="uz-shelfMeta">{searchResults.length} items</div>
            </div>
            {searchResults.length > 0 ? (
              <div className="uz-rack">
                <div className="uz-plank" aria-hidden="true" />
                <div className="uz-mixedRow">
                  {searchResults.map((item, idx) => {
                    const handlers = {
                      onClick: e => openModal(item, e),
                      onMouseEnter: e => handleMouseEnter(e, item),
                      onMouseLeave: handleMouseLeave,
                    };
                    if (item.type === 'featured') {
                      return <BookFace key={`sr-${item.id}-${idx}`} item={item} isHighlighted={false} isFav={isFavorite(item)} {...handlers} />;
                    }
                    return <BookSpine key={`sr-${item.id}-${idx}`} item={item} isHighlighted={false} {...handlers} />;
                  })}
                </div>
              </div>
            ) : (
              <div className="uz-emptyState">
                <div className="uz-emptyState__icon">🔍</div>
                <div className="uz-emptyState__text">該当する本が見つかりませんでした</div>
              </div>
            )}
          </section>
        </div>
      )}

      {/* A4: 記事フィルタバー */}
      {!showArticles && !showFavorites && filterByArticle && mode === 'uz' && (
        <div className="uz-articleFilterBar">
          <span className="uz-articleFilterBar__label">📝 「{truncate(filterArticleTitle, 30)}」の本のみ表示中</span>
          <button className="uz-articleFilterBar__clear" onClick={() => setFilterByArticle(null)}>✕ 解除</button>
        </div>
      )}

      {/* === 1ページ1棚（通常表示） === */}
      {!showArticles && !showFavorites && !searchResults && currentShelf && !isLoading && (
        <div className="uz-singleShelf">
          <section className={`uz-shelf uz-shelf--${currentShelf.id}`}>
            <div className="uz-shelfHead">
              <h2 className="uz-shelfTitle">
                <span className="uz-shelfIcon">{shelfIcons[currentShelf.id] || ''}</span>
                {currentShelf.title}
              </h2>
              <div className="uz-shelfMeta">{currentItems.length} items</div>
            </div>
            <div className="uz-rack">
              <div className="uz-plank" aria-hidden="true" />
              <div className="uz-mixedRow">
                {currentItems.map((item, idx) => {
                  const handlers = {
                    onClick: e => openModal(item, e),
                    onMouseEnter: e => handleMouseEnter(e, item),
                    onMouseLeave: handleMouseLeave,
                  };
                  if (item.type === 'featured') {
                    return <BookFace key={`${item.id}-${idx}`} item={item} isHighlighted={false} isFav={isFavorite(item)} {...handlers} />;
                  }
                  return <BookSpine key={`${item.id}-${idx}`} item={item} isHighlighted={false} {...handlers} />;
                })}
              </div>
            </div>
          </section>

          {/* 前後ナビゲーション */}
          <div className="uz-shelfPager">
            <button
              className="uz-shelfPager__btn"
              onClick={prevShelf}
              disabled={shelfIndex === 0}
            >
              ← {shelfIndex > 0 ? allShelves[shelfIndex - 1].title : ''}
            </button>
            <span className="uz-shelfPager__pos">{shelfIndex + 1} / {allShelves.length}</span>
            <button
              className="uz-shelfPager__btn"
              onClick={nextShelf}
              disabled={shelfIndex === allShelves.length - 1}
            >
              {shelfIndex < allShelves.length - 1 ? allShelves[shelfIndex + 1].title : ''} →
            </button>
          </div>
        </div>
      )}

      {/* ツールチップ — 本棚に重なるオーバーレイカード */}
      {tooltip && (
        <div className="uz-tooltip" style={{
          position: 'fixed',
          left: Math.min(tooltip.x, window.innerWidth - 280),
          top: Math.max(8, tooltip.y - 8),
          transform: 'translate(-50%, -100%)',
          zIndex: 1000, pointerEvents: 'none'
        }}>
          <div className="uz-tooltip__title">{tooltip.item.fullTitle || tooltip.item.title}</div>
          {(tooltip.item.fullAuthor || tooltip.item.author) && (
            <div className="uz-tooltip__author">{tooltip.item.fullAuthor || tooltip.item.author}</div>
          )}
          {tooltip.item.comment && (
            <div className="uz-tooltip__comment">{tooltip.item.comment}</div>
          )}
          {tooltip.item.tags && tooltip.item.tags.length > 0 && (
            <div className="uz-tooltip__tags">
              {tooltip.item.tags.map((tag, i) => (
                <span key={i} className="uz-tooltip__tag">{tag}</span>
              ))}
            </div>
          )}
          {tooltip.item.articleTitle && <div className="uz-tooltip__article">{truncate(tooltip.item.articleTitle, 40)}</div>}
          <div className="uz-tooltip__arrow" />
        </div>
      )}

      {/* モーダル */}
      {modal && (
        <div className="uz-modalOverlay" onClick={closeModal} role="dialog" aria-label="書籍詳細">
          <div className="uz-modal" onClick={e => e.stopPropagation()}>
            <button className="uz-modal__close" onClick={closeModal}>✕</button>
            <div className="uz-modal__inner">
              <div className="uz-modal__cover">
                {modal.coverUrl ? (
                  <img src={modal.coverUrl} alt={modal.fullTitle || modal.title} />
                ) : (
                  <div className="uz-modal__noCover" style={{ background: spineGradient(modal.fullTitle || modal.title) }}>
                    <span>{modal.fullTitle || modal.title}</span>
                  </div>
                )}
              </div>
              <div className="uz-modal__details">
                <h2 className="uz-modal__title">{modal.fullTitle || modal.title}</h2>
                <p className="uz-modal__author">{modal.fullAuthor || modal.author}</p>
                {modal.publisher && <p className="uz-modal__meta">出版社: {modal.publisher}</p>}
                {modal.salesDate && <p className="uz-modal__meta">発売日: {modal.salesDate}</p>}
                {modal.genreId && genreMap[modal.genreId] && <p className="uz-modal__meta">ジャンル: {genreMap[modal.genreId]}</p>}
                {modal.format && FORMAT_SIZES[modal.format] && FORMAT_SIZES[modal.format].label && (
                  <p className="uz-modal__meta">判型: {FORMAT_SIZES[modal.format].label}</p>
                )}
                {modal.price && <p className="uz-modal__price">¥{Number(modal.price).toLocaleString()}</p>}
                {modal.reviewAverage && modal.reviewAverage !== '0' && (
                  <div className="uz-modal__review">
                    <span className="uz-modal__stars">{renderStars(modal.reviewAverage)}</span>
                    <span>{modal.reviewAverage} / 5.0 ({modal.reviewCount}件のレビュー)</span>
                  </div>
                )}
                {modal.caption && <p className="uz-modal__caption">{truncate(modal.caption, 300)}</p>}
                {modal.articleTitle && (() => {
                  const relatedBooks = getArticleBooks(modal.articleId).filter(b =>
                    (b.id || b.fullTitle) !== (modal.id || modal.fullTitle)
                  );
                  return (
                    <div className="uz-modal__articleSection">
                      <div className="uz-modal__articleLink">
                        <span className="uz-modal__articleLabel">関連記事</span>
                        <div className="uz-modal__articleLinkRow">
                          <a href={modal.articleUrl || `/entry/${modal.articleId}`} target="_blank" rel="noopener noreferrer">{modal.articleTitle}</a>
                          <button className="uz-modal__articleDetailBtn" onClick={() => openArticleFromModal(modal.articleId)}>詳細を見る</button>
                        </div>
                      </div>
                      {relatedBooks.length > 0 && (
                        <div className="uz-modal__relatedBooks">
                          <span className="uz-modal__relatedLabel">この記事の他の本（{relatedBooks.length}冊）</span>
                          <div className="uz-modal__relatedList">
                            {relatedBooks.slice(0, 8).map((b, i) => (
                              <button
                                key={`rel-${b.id}-${i}`}
                                className="uz-modal__relatedThumb"
                                onClick={() => setModal(b)}
                                title={b.fullTitle || b.title}
                              >
                                {b.coverUrl ? (
                                  <img src={b.coverUrl} alt="" />
                                ) : (
                                  <div className="uz-modal__relatedPlaceholder" style={{ background: spineGradient(b.fullTitle || b.title) }}>
                                    <span>{truncate(b.title, 6)}</span>
                                  </div>
                                )}
                              </button>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })()}
                <div className="uz-modal__actions">
                  {/* お気に入りボタン (A6) */}
                  <button
                    className={`uz-modal__btn uz-modal__btn--fav ${isFavorite(modal) ? 'active' : ''}`}
                    onClick={() => toggleFavorite(modal)}
                  >
                    {isFavorite(modal) ? '♥ お気に入り済み' : '♡ お気に入りに追加'}
                  </button>
                  {(modal.affiliateUrl || modal.url) && <a href={modal.affiliateUrl || modal.url} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--rakuten">楽天で購入</a>}
                  {modal.amazonUrl && <a href={modal.amazonUrl} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--amazon">Amazonで見る</a>}
                  {modal.rakutenUrl && !modal.affiliateUrl && <a href={modal.rakutenUrl} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--rakuten">楽天で見る</a>}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Mount is handled by WordPress shortcode initializer
// For standalone use: ReactDOM.createRoot(document.getElementById('root')).render(<UzBookshelf />);
