// ============================================================
// UZ Bookshelf — 統合UI
// - 楽天Books棚（既存機能 + レビュー・価格・アフィリエイト反映）
// - uzブログ連動棚（小説・マンガ・映画DVD・音楽・テクノロジー）
// - 記事一覧パネル（記事→棚アイテムへのリンク）
// - 書籍詳細モーダル
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
  return `hsl(${h} 35% 42%)`;
}

function shuffleArray(array) {
  const shuffled = [...array];
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  return shuffled;
}

function chunkArray(array, chunkSize) {
  const chunks = [];
  for (let i = 0; i < array.length; i += chunkSize) {
    chunks.push(array.slice(i, i + chunkSize));
  }
  return chunks;
}

function truncate(str, len) {
  if (!str) return '';
  return str.length > len ? str.substring(0, len) + '...' : str;
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

// ============================================================
// メインコンポーネント
// ============================================================
function UzBookshelf() {
  // --- State ---
  const [mode, setMode] = React.useState('uz'); // 'rakuten' | 'uz'
  const [rakutenData, setRakutenData] = React.useState(null);
  const [uzData, setUzData] = React.useState(null);
  const [genreMap, setGenreMap] = React.useState({});
  const [modal, setModal] = React.useState(null);
  const [tooltip, setTooltip] = React.useState(null);
  const [activeShelf, setActiveShelf] = React.useState(null);
  const [showArticles, setShowArticles] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState('');
  const [highlightArticle, setHighlightArticle] = React.useState(null);
  const tooltipTimeoutRef = React.useRef(null);

  const handleMouseEnter = (e, item) => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    tooltipTimeoutRef.current = setTimeout(() => {
      setTooltip({ x: e.clientX, y: e.clientY, item });
    }, 400);
  };
  const handleMouseLeave = () => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    setTooltip(null);
  };

  // --- 楽天データ読み込み ---
  React.useEffect(() => {
    const genrePromises = [
      fetch('001005genre.json').then(r => r.json()),
      fetch('001006genre.json').then(r => r.json()),
      fetch('001010genre.json').then(r => r.json()),
    ];
    Promise.all(genrePromises).then(([g5, g6, g10]) => {
      const map = {};
      [g5, g6, g10].forEach(g => {
        map[g.current.booksGenreId] = g.current.booksGenreName;
        g.children.forEach(c => {
          map[c.child.booksGenreId] = c.child.booksGenreName;
        });
      });
      setGenreMap(map);
    }).catch(e => console.error('Genre load error:', e));
  }, []);

  React.useEffect(() => {
    if (Object.keys(genreMap).length === 0) return;
    const bookPromises = [
      fetch('001005.json').then(r => r.json()),
      fetch('001006.json').then(r => r.json()),
      fetch('001010.json').then(r => r.json()),
    ];
    Promise.all(bookPromises).then(([j5, j6, j10]) => {
      const grouped = { tech: [], biz: [], culture: [] };
      const allBooks = [];
      const configs = [
        { json: j5, shelf: 'tech' },
        { json: j6, shelf: 'biz' },
        { json: j10, shelf: 'culture' },
      ];
      configs.forEach(({ json, shelf }) => {
        json.Items.forEach(item => {
          const b = item.Item;
          const bookData = {
            id: b.isbn,
            title: truncate(b.title, 20),
            fullTitle: b.title,
            author: truncate(b.author, 16),
            fullAuthor: b.author,
            coverUrl: b.largeImageUrl,
            url: b.itemUrl,
            affiliateUrl: b.affiliateUrl || '',
            isbn: b.isbn,
            price: b.itemPrice,
            reviewAverage: b.reviewAverage,
            reviewCount: b.reviewCount,
            caption: b.itemCaption || '',
            publisher: b.publisherName || '',
            salesDate: b.salesDate || '',
            genreId: b.booksGenreId || '',
            shelf,
            source: 'rakuten',
          };
          grouped[shelf].push(bookData);
          allBooks.push(bookData);
        });
      });

      // Set data immediately so UI renders, then enrich asynchronously
      const finalData = [
        { id: 'tech', title: 'テクノロジー', books: grouped.tech },
        { id: 'biz', title: 'ビジネス', books: grouped.biz },
        { id: 'culture', title: 'カルチャー', books: grouped.culture },
      ];
      setRakutenData(finalData);

      // openBD enrichment (async, non-blocking)
      const isbns = allBooks.map(b => b.isbn);
      const chunks = chunkArray(isbns, 30);
      Promise.all(
        chunks.map(chunk =>
          fetch(`https://api.openbd.jp/v1/get?isbn=${chunk.join(',')}`)
            .then(r => r.json())
            .catch(() => [])
        )
      ).then(results => {
        const openbdData = {};
        results.forEach(result => {
          if (!Array.isArray(result)) return;
          result.forEach(book => {
            if (book && book.summary) {
              openbdData[book.summary.isbn] = { pages: book.summary.pages, size: book.summary.size };
            }
          });
        });
        allBooks.forEach(book => {
          if (openbdData[book.isbn]) {
            book.pages = openbdData[book.isbn].pages;
            book.size = openbdData[book.isbn].size;
          }
        });
        // image aspect ratio (with timeout fallback)
        const imgTimeout = 3000;
        return Promise.all(allBooks.map(book => new Promise(resolve => {
          const img = new Image();
          const timer = setTimeout(() => { book.aspectRatio = 0.7; resolve(); }, imgTimeout);
          img.onload = () => { clearTimeout(timer); book.aspectRatio = img.naturalWidth / img.naturalHeight; resolve(); };
          img.onerror = () => { clearTimeout(timer); book.aspectRatio = 0.7; resolve(); };
          img.src = book.coverUrl;
        })));
      }).then(() => {
        // Re-trigger render with enriched data
        setRakutenData([...finalData]);
      }).catch(() => {});
    }).catch(e => console.error('Book load error:', e));
  }, [genreMap]);

  // --- uzデータ読み込み ---
  React.useEffect(() => {
    fetch('uz-shelf-data.json')
      .then(r => r.json())
      .then(data => setUzData(data))
      .catch(e => console.error('uz data load error:', e));
  }, []);

  // --- サイズスタイル ---
  const getSizeStyle = (size, pages, aspectRatio) => {
    let width = 112, height = 148;
    if (size === 'コミック') { width = 100; height = 160; }
    else if (size === 'B6') { width = 105; height = 140; }
    if (aspectRatio && aspectRatio > 0) height = width / aspectRatio;
    const thickness = Math.min((pages || 200) / 200, 1) * 10;
    return { width: `${width}px`, height: `${height}px`, thickness };
  };

  // --- 楽天棚の描画 ---
  const rakutenShelves = React.useMemo(() => {
    if (!rakutenData) return [];
    return rakutenData.map(s => {
      const featured = s.books.slice(0, 3).map(b => ({ ...b, type: 'featured' }));
      let rest = s.books.slice(3, 27).map(b => ({ ...b, type: 'spine' }));
      if (rest.length > 3) {
        const shuffledRest = shuffleArray(rest);
        const randomFeatured = shuffledRest.slice(0, 3).map(b => ({ ...b, type: 'featured' }));
        const remainingSpines = shuffledRest.slice(3).map(b => ({ ...b, type: 'spine' }));
        rest = [...randomFeatured, ...remainingSpines];
      }
      return { ...s, mixedBooks: shuffleArray([...featured, ...rest]) };
    });
  }, [rakutenData]);

  // --- uz棚の描画 ---
  const uzShelves = React.useMemo(() => {
    if (!uzData) return [];
    return uzData.shelves.filter(s => s.items.length > 0).map(s => {
      const items = s.items.map((item, i) => ({
        ...item,
        type: i < 4 || Math.random() > 0.6 ? 'featured' : 'spine',
        source: 'uz',
      }));
      return { ...s, mixedItems: shuffleArray(items) };
    });
  }, [uzData]);

  // --- 記事一覧（フィルタ付き） ---
  const filteredArticles = React.useMemo(() => {
    if (!uzData) return [];
    let articles = uzData.articles;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      articles = articles.filter(a =>
        a.title.toLowerCase().includes(q) ||
        a.categories.some(c => c.toLowerCase().includes(q))
      );
    }
    if (activeShelf && activeShelf !== 'all') {
      articles = articles.filter(a => a.shelf === activeShelf);
    }
    return articles;
  }, [uzData, searchQuery, activeShelf]);

  // --- モーダル ---
  const openModal = (item, e) => {
    if (e) e.preventDefault();
    setModal(item);
  };
  const closeModal = () => setModal(null);

  // --- 記事からアイテムへジャンプ ---
  const jumpToShelfFromArticle = (articleId) => {
    setShowArticles(false);
    setHighlightArticle(articleId);
    setTimeout(() => setHighlightArticle(null), 3000);
  };

  // --- シェルフアイコン ---
  const shelfIcons = {
    books: '📚', manga: '📖', film: '🎬', music: '🎵', tech: '💻', culture: '🌍',
  };

  // ============================================================
  // RENDER
  // ============================================================
  return (
    <div className="uz-wrap">
      {/* ヘッダー */}
      <header className="uz-header">
        <div>
          <div className="uz-kicker">UZ MEDIA</div>
          <h1 className="uz-title">UZ Bookshelf</h1>
        </div>
        <div className="uz-headerActions">
          <button
            className={`uz-tabBtn ${mode === 'uz' ? 'active' : ''}`}
            onClick={() => setMode('uz')}
          >
            UZ セレクション
          </button>
          <button
            className={`uz-tabBtn ${mode === 'rakuten' ? 'active' : ''}`}
            onClick={() => setMode('rakuten')}
          >
            楽天Books
          </button>
          {mode === 'uz' && (
            <button
              className={`uz-tabBtn ${showArticles ? 'active' : ''}`}
              onClick={() => setShowArticles(!showArticles)}
            >
              記事一覧
            </button>
          )}
        </div>
      </header>

      {/* 記事一覧パネル */}
      {showArticles && uzData && (
        <div className="uz-articlesPanel">
          <div className="uz-articlesPanelHead">
            <h2>UZ 記事一覧</h2>
            <input
              className="uz-searchInput"
              type="text"
              placeholder="記事を検索..."
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
            />
            <div className="uz-shelfFilter">
              <button className={`uz-filterBtn ${!activeShelf || activeShelf === 'all' ? 'active' : ''}`} onClick={() => setActiveShelf('all')}>すべて</button>
              {uzData.shelves.map(s => (
                <button key={s.id} className={`uz-filterBtn ${activeShelf === s.id ? 'active' : ''}`} onClick={() => setActiveShelf(s.id)}>
                  {s.title}
                </button>
              ))}
            </div>
          </div>
          <div className="uz-articlesList">
            {filteredArticles.map(art => (
              <div key={art.id} className="uz-articleCard" onClick={() => jumpToShelfFromArticle(art.id)}>
                <div className="uz-articleCard__icon">{shelfIcons[art.shelf] || '📄'}</div>
                <div className="uz-articleCard__body">
                  <div className="uz-articleCard__title">{art.title}</div>
                  <div className="uz-articleCard__meta">
                    <span>{formatDate(art.date)}</span>
                    {art.categories.map(c => (
                      <span key={c} className="uz-articleCard__cat">{c}</span>
                    ))}
                    <span className="uz-articleCard__count">{art.productCount}点</span>
                  </div>
                </div>
                <a href={art.url} target="_blank" rel="noopener noreferrer" className="uz-articleCard__link" onClick={e => e.stopPropagation()}>
                  記事を読む →
                </a>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* === UZ セレクション棚 === */}
      {mode === 'uz' && !showArticles && (
        <div className="uz-grid uz-grid--uz">
          {uzShelves.map(shelf => (
            <section key={shelf.id} className={`uz-shelf uz-shelf--${shelf.id}`}>
              <div className="uz-shelfHead">
                <h2 className="uz-shelfTitle">
                  <span className="uz-shelfIcon">{shelfIcons[shelf.id] || ''}</span>
                  {shelf.title}
                </h2>
                <div className="uz-shelfMeta">{shelf.items.length} items</div>
              </div>
              <div className="uz-rack">
                <div className="uz-plank" aria-hidden="true" />
                <div className="uz-mixedRow">
                  {shelf.mixedItems.map((item, idx) => {
                    const isHighlighted = highlightArticle && item.articleId === highlightArticle;
                    if (item.type === 'featured' && item.coverUrl) {
                      return (
                        <a
                          key={`${item.id}-${idx}`}
                          className={`uz-bookFace ${isHighlighted ? 'uz-highlight' : ''}`}
                          href="#"
                          onClick={e => openModal(item, e)}
                          onMouseEnter={e => handleMouseEnter(e, item)}
                          onMouseLeave={handleMouseLeave}
                        >
                          <div className="uz-bookFace__cover" style={{ backgroundImage: `url(${item.coverUrl})` }} />
                          <div className="uz-bookFace__edge" aria-hidden="true" />
                          <div className="uz-bookFace__shadow" aria-hidden="true" />
                        </a>
                      );
                    } else {
                      return (
                        <a
                          key={`${item.id}-${idx}`}
                          className={`uz-spine ${isHighlighted ? 'uz-highlight' : ''}`}
                          href="#"
                          onClick={e => openModal(item, e)}
                          style={{ backgroundColor: spineColorFromTitle(item.fullTitle || item.title) }}
                          onMouseEnter={e => handleMouseEnter(e, item)}
                          onMouseLeave={handleMouseLeave}
                        >
                          <div className="uz-spine__side" aria-hidden="true" />
                          <div className="uz-spine__text">
                            <span className="uz-spine__title">{truncate(item.title, 14)}</span>
                            <span className="uz-spine__author">{truncate(item.author, 8)}</span>
                          </div>
                        </a>
                      );
                    }
                  })}
                </div>
              </div>
            </section>
          ))}
        </div>
      )}

      {/* === 楽天Books棚 === */}
      {mode === 'rakuten' && (
        <div className="uz-grid">
          {rakutenShelves.map(shelf => (
            <section key={shelf.id} className="uz-shelf">
              <div className="uz-shelfHead">
                <h2 className="uz-shelfTitle">{shelf.title}</h2>
                <div className="uz-shelfMeta">{shelf.books.length} items</div>
              </div>
              <div className="uz-rack">
                <div className="uz-plank" aria-hidden="true" />
                <div className="uz-mixedRow">
                  {shelf.mixedBooks.map((b) => {
                    const sizeStyle = getSizeStyle(b.size, b.pages, b.aspectRatio);
                    if (b.type === 'featured') {
                      return (
                        <a
                          key={b.id}
                          className="uz-bookFace"
                          href="#"
                          onClick={e => openModal(b, e)}
                          style={{ width: sizeStyle.width, height: sizeStyle.height }}
                          onMouseEnter={e => handleMouseEnter(e, b)}
                          onMouseLeave={handleMouseLeave}
                        >
                          <div className="uz-bookFace__cover" style={{ backgroundImage: `url(${b.coverUrl})` }} />
                          <div className="uz-bookFace__edge" aria-hidden="true" />
                          <div className="uz-bookFace__shadow" aria-hidden="true" style={{ filter: `blur(${sizeStyle.thickness}px)` }} />
                          {b.reviewAverage && b.reviewAverage !== '0' && (
                            <div className="uz-bookFace__badge">
                              {renderStars(b.reviewAverage)}
                            </div>
                          )}
                        </a>
                      );
                    } else {
                      return (
                        <a
                          key={b.id}
                          className="uz-spine"
                          href="#"
                          onClick={e => openModal(b, e)}
                          style={{
                            backgroundColor: spineColorFromTitle(b.fullTitle || b.title),
                            width: sizeStyle.thickness,
                          }}
                          onMouseEnter={e => handleMouseEnter(e, b)}
                          onMouseLeave={handleMouseLeave}
                        >
                          <div className="uz-spine__side" aria-hidden="true" />
                          <div className="uz-spine__text">
                            <span className="uz-spine__title">{b.title}</span>
                            <span className="uz-spine__author">{b.author}</span>
                          </div>
                        </a>
                      );
                    }
                  })}
                </div>
              </div>
            </section>
          ))}
        </div>
      )}

      {/* === ツールチップ === */}
      {tooltip && (
        <div className="uz-tooltip" style={{
          position: 'fixed', left: tooltip.x + 12, top: tooltip.y + 12,
          zIndex: 1000, pointerEvents: 'none',
        }}>
          <div className="uz-tooltip__title">{tooltip.item.fullTitle || tooltip.item.title}</div>
          <div className="uz-tooltip__author">{tooltip.item.fullAuthor || tooltip.item.author}</div>
          {tooltip.item.price && <div className="uz-tooltip__price">¥{Number(tooltip.item.price).toLocaleString()}</div>}
          {tooltip.item.reviewAverage && tooltip.item.reviewAverage !== '0' && (
            <div className="uz-tooltip__review">{renderStars(tooltip.item.reviewAverage)} ({tooltip.item.reviewCount}件)</div>
          )}
          {tooltip.item.size && <div>サイズ: {tooltip.item.size}</div>}
          {tooltip.item.pages && <div>ページ: {tooltip.item.pages}</div>}
          {tooltip.item.articleTitle && (
            <div className="uz-tooltip__article">関連記事: {truncate(tooltip.item.articleTitle, 30)}</div>
          )}
        </div>
      )}

      {/* === モーダル === */}
      {modal && (
        <div className="uz-modalOverlay" onClick={closeModal}>
          <div className="uz-modal" onClick={e => e.stopPropagation()}>
            <button className="uz-modal__close" onClick={closeModal}>✕</button>
            <div className="uz-modal__inner">
              {/* 左: 表紙 */}
              <div className="uz-modal__cover">
                {modal.coverUrl ? (
                  <img src={modal.coverUrl} alt={modal.fullTitle || modal.title} />
                ) : (
                  <div className="uz-modal__noCover" style={{ backgroundColor: spineColorFromTitle(modal.fullTitle || modal.title) }}>
                    <span>{modal.fullTitle || modal.title}</span>
                  </div>
                )}
              </div>
              {/* 右: 詳細 */}
              <div className="uz-modal__details">
                <h2 className="uz-modal__title">{modal.fullTitle || modal.title}</h2>
                <p className="uz-modal__author">{modal.fullAuthor || modal.author}</p>

                {modal.publisher && <p className="uz-modal__meta">出版社: {modal.publisher}</p>}
                {modal.salesDate && <p className="uz-modal__meta">発売日: {modal.salesDate}</p>}
                {modal.genreId && genreMap[modal.genreId] && (
                  <p className="uz-modal__meta">ジャンル: {genreMap[modal.genreId]}</p>
                )}
                {modal.price && (
                  <p className="uz-modal__price">¥{Number(modal.price).toLocaleString()}</p>
                )}
                {modal.reviewAverage && modal.reviewAverage !== '0' && (
                  <div className="uz-modal__review">
                    <span className="uz-modal__stars">{renderStars(modal.reviewAverage)}</span>
                    <span>{modal.reviewAverage} / 5.0 ({modal.reviewCount}件のレビュー)</span>
                  </div>
                )}
                {modal.caption && (
                  <p className="uz-modal__caption">{truncate(modal.caption, 300)}</p>
                )}

                {/* 記事リンク */}
                {modal.articleTitle && (
                  <div className="uz-modal__articleLink">
                    <span className="uz-modal__articleLabel">関連記事</span>
                    <a href={`https://uz-media.com/entry/${modal.articleId}`} target="_blank" rel="noopener noreferrer">
                      {modal.articleTitle}
                    </a>
                  </div>
                )}

                {/* 購入ボタン */}
                <div className="uz-modal__actions">
                  {(modal.affiliateUrl || modal.url) && (
                    <a href={modal.affiliateUrl || modal.url} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--rakuten">
                      楽天で購入
                    </a>
                  )}
                  {modal.amazonUrl && (
                    <a href={modal.amazonUrl} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--amazon">
                      Amazonで見る
                    </a>
                  )}
                  {modal.rakutenUrl && !modal.affiliateUrl && (
                    <a href={modal.rakutenUrl} target="_blank" rel="noopener noreferrer" className="uz-modal__btn uz-modal__btn--rakuten">
                      楽天で見る
                    </a>
                  )}
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
