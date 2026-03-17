// ============================================================
// UZ Bookshelf — 統合UI v2
// 美術的品質向上: 表紙3D, 判型反映, 光沢/陰影, 背表紙グラデ
// ============================================================

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
  const [highlightArticle, setHighlightArticle] = React.useState(null);
  const [shelfIndex, setShelfIndex] = React.useState(0);
  const [expandedArticle, setExpandedArticle] = React.useState(null);
  const tooltipTimeoutRef = React.useRef(null);

  const handleMouseEnter = (e, item) => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    tooltipTimeoutRef.current = setTimeout(() => {
      setTooltip({ x: e.clientX, y: e.clientY, item });
    }, 350);
  };
  const handleMouseLeave = () => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    setTooltip(null);
  };

  // --- 楽天データ ---
  React.useEffect(() => {
    Promise.all([
      fetch('001005genre.json').then(r => r.json()),
      fetch('001006genre.json').then(r => r.json()),
      fetch('001010genre.json').then(r => r.json()),
    ]).then(([g5, g6, g10]) => {
      const map = {};
      [g5, g6, g10].forEach(g => {
        map[g.current.booksGenreId] = g.current.booksGenreName;
        g.children.forEach(c => { map[c.child.booksGenreId] = c.child.booksGenreName; });
      });
      setGenreMap(map);
    }).catch(e => console.error('Genre load error:', e));
  }, []);

  React.useEffect(() => {
    if (Object.keys(genreMap).length === 0) return;
    Promise.all([
      fetch('001005.json').then(r => r.json()),
      fetch('001006.json').then(r => r.json()),
      fetch('001010.json').then(r => r.json()),
    ]).then(([j5, j6, j10]) => {
      const grouped = { tech: [], biz: [], culture: [] };
      const allBooks = [];
      [{ json: j5, shelf: 'tech' }, { json: j6, shelf: 'biz' }, { json: j10, shelf: 'culture' }]
        .forEach(({ json, shelf }) => {
          json.Items.forEach(item => {
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
            allBooks.push(bookData);
          });
        });

      setRakutenData([
        { id: 'tech', title: 'テクノロジー', books: grouped.tech },
        { id: 'biz', title: 'ビジネス', books: grouped.biz },
        { id: 'culture', title: 'カルチャー', books: grouped.culture },
      ]);
    }).catch(e => console.error('Book load error:', e));
  }, [genreMap]);

  // --- uzデータ ---
  React.useEffect(() => {
    fetch('uz-shelf-data.json').then(r => r.json()).then(data => setUzData(data)).catch(e => console.error('uz data:', e));
  }, []);

  // --- 棚データ構築 ---
  const rakutenShelves = React.useMemo(() => {
    if (!rakutenData) return [];
    return rakutenData.map(s => {
      const books = s.books.slice(0, 30).map(b => ({
        ...b,
        type: b.coverUrl ? 'featured' : 'spine',
      }));
      return { ...s, mixedBooks: books };
    });
  }, [rakutenData]);

  const uzShelves = React.useMemo(() => {
    if (!uzData) return [];
    return uzData.shelves.filter(s => s.items.length > 0).map(s => {
      const isFilm = s.id === 'film';
      const items = s.items.map((item) => ({
        ...item,
        type: item.coverUrl ? 'featured' : 'spine',
        source: 'uz',
        shelfId: s.id,
        format: isFilm ? 'poster' : (item.format || detectFormat(item.fullTitle || item.title)),
      }));
      return { ...s, mixedItems: items };
    });
  }, [uzData]);

  const filteredArticles = React.useMemo(() => {
    if (!uzData) return [];
    let articles = uzData.articles;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      articles = articles.filter(a => a.title.toLowerCase().includes(q) || a.categories.some(c => c.toLowerCase().includes(q)) || (a.themes && a.themes.some(t => t.toLowerCase().includes(q))));
    }
    if (activeShelf && activeShelf !== 'all') articles = articles.filter(a => a.shelf === activeShelf);
    return articles;
  }, [uzData, searchQuery, activeShelf]);

  // --- 棚ナビゲーション ---
  const allShelves = React.useMemo(() => {
    if (mode === 'uz') return uzShelves;
    return rakutenShelves;
  }, [mode, uzShelves, rakutenShelves]);

  const currentShelf = allShelves[shelfIndex] || null;

  const goToShelf = (idx) => {
    setShelfIndex(idx);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const prevShelf = () => goToShelf(Math.max(0, shelfIndex - 1));
  const nextShelf = () => goToShelf(Math.min(allShelves.length - 1, shelfIndex + 1));

  // モード切替時にリセット
  const switchMode = (newMode) => {
    setMode(newMode);
    setShelfIndex(0);
    setShowArticles(false);
  };

  const openModal = (item, e) => { if (e) e.preventDefault(); setModal(item); };
  const closeModal = () => setModal(null);
  const jumpToShelfFromArticle = (articleId) => {
    setShowArticles(false); setHighlightArticle(articleId);
    setTimeout(() => setHighlightArticle(null), 3000);
  };

  const shelfIcons = { books: '📚', manga: '📖', film: '🎬', music: '🎵', tech: '💻', biz: '💼', culture: '🌍' };

  // --- テーマが共通する関連記事を取得 ---
  const getRelatedArticles = React.useCallback((article) => {
    if (!uzData || !article.themes || article.themes.length === 0) return [];
    const myThemes = new Set(article.themes);
    const scored = [];
    for (const other of uzData.articles) {
      if (other.id === article.id) continue;
      if (!other.themes || other.themes.length === 0) continue;
      const shared = other.themes.filter(t => myThemes.has(t));
      if (shared.length > 0) {
        scored.push({ ...other, sharedThemes: shared, sharedCount: shared.length });
      }
    }
    scored.sort((a, b) => b.sharedCount - a.sharedCount);
    return scored.slice(0, 5);
  }, [uzData]);

  // ============================================================
  // 表紙コンポーネント — 美術的3D
  // ============================================================
  const BookFace = ({ item, isHighlighted, onClick, onMouseEnter, onMouseLeave }) => {
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
          {/* 表紙面 */}
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
            {/* 光沢オーバーレイ */}
            <div className="uz-book__gloss" />
          </div>
          {/* 背表紙面（右端） */}
          <div className="uz-book__spine" style={{ background: spineGradient(item.fullTitle || item.title), width: dim.thickness }} />
          {/* ページ断面（上部） */}
          {!isDisc && !isPoster && <div className="uz-book__pages" style={{ height: dim.thickness }} />}
          {/* レビューバッジ */}
          {item.reviewAverage && item.reviewAverage !== '0' && (
            <div className="uz-book__badge">{renderStars(item.reviewAverage)}</div>
          )}
        </div>
        {/* 影 */}
        <div className="uz-book__shadow" />
      </a>
    );
  };

  // ============================================================
  // 背表紙コンポーネント — リアルな質感
  // ============================================================
  const BookSpine = ({ item, isHighlighted, onClick, onMouseEnter, onMouseLeave }) => {
    const dim = getBookDimensions(item);
    const color = spineColorFromTitle(item.fullTitle || item.title);
    const isDisc = dim.format === 'disc' || dim.format === 'poster';
    const thickness = isDisc ? 8 : dim.thickness;

    return (
      <a
        className={`uz-spine2 ${isHighlighted ? 'uz-highlight' : ''} ${isDisc ? 'uz-spine2--disc' : ''}`}
        href="#" onClick={onClick} onMouseEnter={onMouseEnter} onMouseLeave={onMouseLeave}
        style={{ width: thickness, height: dim.height }}
      >
        <div className="uz-spine2__body" style={{ background: spineGradient(item.fullTitle || item.title) }}>
          {/* テクスチャ */}
          <div className="uz-spine2__texture" />
          {/* テキスト */}
          <div className="uz-spine2__text">
            <span className="uz-spine2__title">{truncate(item.fullTitle || item.title, 16)}</span>
            {(item.author || item.fullAuthor) && (
              <span className="uz-spine2__author">{truncate(item.fullAuthor || item.author, 10)}</span>
            )}
          </div>
          {/* 端の丸み・光沢 */}
          <div className="uz-spine2__edge" />
          <div className="uz-spine2__gloss" />
        </div>
      </a>
    );
  };

  // --- 棚の本リスト（UZ/楽天で統一） ---
  const currentItems = React.useMemo(() => {
    if (!currentShelf) return [];
    return currentShelf.mixedItems || currentShelf.mixedBooks || [];
  }, [currentShelf]);

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
          <button className={`uz-tabBtn ${mode === 'uz' ? 'active' : ''}`} onClick={() => switchMode('uz')}>UZ セレクション</button>
          <button className={`uz-tabBtn ${mode === 'rakuten' ? 'active' : ''}`} onClick={() => switchMode('rakuten')}>楽天Books</button>
          {mode === 'uz' && (
            <button className={`uz-tabBtn ${showArticles ? 'active' : ''}`} onClick={() => setShowArticles(!showArticles)}>記事一覧</button>
          )}
        </div>
      </header>

      {/* 棚セレクター */}
      {!showArticles && allShelves.length > 0 && (
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

      {/* 記事一覧パネル */}
      {showArticles && uzData && (
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
          </div>
          <div className="uz-articlesList">
            {filteredArticles.map(art => {
              const isExpanded = expandedArticle === art.id;
              const related = isExpanded ? getRelatedArticles(art) : [];
              return (
                <div key={art.id} className={`uz-articleCard__wrap ${isExpanded ? 'expanded' : ''}`}>
                  <div className="uz-articleCard" onClick={() => setExpandedArticle(isExpanded ? null : art.id)}>
                    <div className="uz-articleCard__icon">{shelfIcons[art.shelf] || '📄'}</div>
                    <div className="uz-articleCard__body">
                      <div className="uz-articleCard__title">{art.title}</div>
                      <div className="uz-articleCard__meta">
                        <span>{formatDate(art.date)}</span>
                        {art.categories.map(c => <span key={c} className="uz-articleCard__cat">{c}</span>)}
                        <span className="uz-articleCard__count">{art.productCount}点</span>
                      </div>
                      {art.themes && art.themes.length > 0 && (
                        <div className="uz-articleCard__themes">
                          {art.themes.map(t => <span key={t} className="uz-articleCard__theme">{t}</span>)}
                        </div>
                      )}
                    </div>
                    <div className="uz-articleCard__actions">
                      <a href={art.url} target="_blank" rel="noopener noreferrer" className="uz-articleCard__link" onClick={e => e.stopPropagation()}>記事を読む →</a>
                      <span className={`uz-articleCard__expand ${isExpanded ? 'open' : ''}`}>▼</span>
                    </div>
                  </div>
                  {isExpanded && (
                    <div className="uz-relatedSection">
                      {related.length > 0 && (
                        <>
                          <div className="uz-relatedSection__heading">テーマが共通する記事</div>
                          <div className="uz-relatedSection__list">
                            {related.map(rel => (
                              <a key={rel.id} href={rel.url} target="_blank" rel="noopener noreferrer" className="uz-relatedItem" onClick={e => e.stopPropagation()}>
                                <span className="uz-relatedItem__icon">{shelfIcons[rel.shelf] || '📄'}</span>
                                <span className="uz-relatedItem__body">
                                  <span className="uz-relatedItem__title">{rel.title}</span>
                                  <span className="uz-relatedItem__shared">{rel.sharedThemes.join(' / ')}</span>
                                </span>
                              </a>
                            ))}
                          </div>
                        </>
                      )}
                      <button className="uz-relatedSection__backBtn" onClick={(e) => { e.stopPropagation(); setShowArticles(false); }}>
                        ← 本棚に戻る
                      </button>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* === 1ページ1棚 === */}
      {!showArticles && currentShelf && (
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
                  const isHl = highlightArticle && item.articleId === highlightArticle;
                  const handlers = {
                    onClick: e => openModal(item, e),
                    onMouseEnter: e => handleMouseEnter(e, item),
                    onMouseLeave: handleMouseLeave,
                  };
                  if (item.type === 'featured') {
                    return <BookFace key={`${item.id}-${idx}`} item={item} isHighlighted={isHl} {...handlers} />;
                  }
                  return <BookSpine key={`${item.id}-${idx}`} item={item} isHighlighted={isHl} {...handlers} />;
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

      {/* ツールチップ */}
      {tooltip && (
        <div className="uz-tooltip" style={{ position: 'fixed', left: tooltip.x + 14, top: tooltip.y + 14, zIndex: 1000, pointerEvents: 'none' }}>
          <div className="uz-tooltip__title">{tooltip.item.fullTitle || tooltip.item.title}</div>
          <div className="uz-tooltip__author">{tooltip.item.fullAuthor || tooltip.item.author}</div>
          {tooltip.item.price && <div className="uz-tooltip__price">¥{Number(tooltip.item.price).toLocaleString()}</div>}
          {tooltip.item.reviewAverage && tooltip.item.reviewAverage !== '0' && (
            <div className="uz-tooltip__review">{renderStars(tooltip.item.reviewAverage)} ({tooltip.item.reviewCount}件)</div>
          )}
          {tooltip.item.format && FORMAT_SIZES[tooltip.item.format] && FORMAT_SIZES[tooltip.item.format].label && (
            <div className="uz-tooltip__format">{FORMAT_SIZES[tooltip.item.format].label}</div>
          )}
          {tooltip.item.articleTitle && <div className="uz-tooltip__article">📝 {truncate(tooltip.item.articleTitle, 30)}</div>}
        </div>
      )}

      {/* モーダル */}
      {modal && (
        <div className="uz-modalOverlay" onClick={closeModal}>
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
                {modal.articleTitle && (
                  <div className="uz-modal__articleLink">
                    <span className="uz-modal__articleLabel">関連記事</span>
                    <a href={`https://uz-media.com/entry/${modal.articleId}`} target="_blank" rel="noopener noreferrer">{modal.articleTitle}</a>
                  </div>
                )}
                <div className="uz-modal__actions">
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

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<UzBookshelf />);
