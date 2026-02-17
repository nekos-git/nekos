// ---- ダミーデータ（UI確認用） ----
const demoShelves = [
  {
    id: "tech",
    title: "テクノロジー",
    books: Array.from({ length: 22 }).map((_, i) => ({
      id: `tech-${i}`,
      title: `技術の本 ${i + 1}`,
      author: "著者名",
      // 画像は後で楽天書影URLなどに置き換え
      coverUrl:
        i % 4 === 0
          ? "https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?auto=format&fit=crop&w=500&q=60"
          : i % 4 === 1
          ? "https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=500&q=60"
          : i % 4 === 2
          ? "https://images.unsplash.com/photo-1495446815901-a7297e633e8d?auto=format&fit=crop&w=500&q=60"
          : "https://images.unsplash.com/photo-1455885666463-5c0f3b2c0d1b?auto=format&fit=crop&w=500&q=60",
      url: "#",
    })),
  },
  {
    id: "biz",
    title: "ビジネス",
    books: Array.from({ length: 20 }).map((_, i) => ({
      id: `biz-${i}`,
      title: `仕事の本 ${i + 1}`,
      author: "著者名",
      coverUrl:
        i % 3 === 0
          ? "https://images.unsplash.com/photo-1524578271613-d550eacf6090?auto=format&fit=crop&w=500&q=60"
          : i % 3 === 1
          ? "https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=500&q=60"
          : "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=500&q=60",
      url: "#",
    })),
  },
  {
    id: "culture",
    title: "カルチャー",
    books: Array.from({ length: 18 }).map((_, i) => ({
      id: `culture-${i}`,
      title: `文化の本 ${i + 1}`,
      author: "著者名",
      coverUrl:
        i % 2 === 0
          ? "https://images.unsplash.com/photo-1491841573634-28140fc7ced7?auto=format&fit=crop&w=500&q=60"
          : "https://images.unsplash.com/photo-1528207776546-365bb710ee93?auto=format&fit=crop&w=500&q=60",
      url: "#",
    })),
  },
];

// ---- ユーティリティ ----
function stableHash(str) {
  // 安定した色生成用の簡易ハッシュ（UI用）
  let h = 2166136261;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return Math.abs(h);
}

function spineColorFromTitle(title) {
  const h = stableHash(title) % 360;
  // 彩度と明度を固定して棚に統一感を出す
  return `hsl(${h} 35% 42%)`;
}

function Bookshelf3D({
  shelves = demoShelves,
  featuredCount = 3,
  maxSpines = 24,
  randomFeaturedCount = 3,
}) {
  const [data, setData] = React.useState(null);
  const [genreMap, setGenreMap] = React.useState({});
  const [tooltip, setTooltip] = React.useState(null);
  const tooltipTimeoutRef = React.useRef(null);

  const handleMouseEnter = (e, book) => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    tooltipTimeoutRef.current = setTimeout(() => {
      setTooltip({
        x: e.clientX,
        y: e.clientY,
        book,
      });
    }, 500); // 0.5秒遅延
  };

  const handleMouseLeave = () => {
    if (tooltipTimeoutRef.current) clearTimeout(tooltipTimeoutRef.current);
    setTooltip(null);
  };

  // サイズに基づく比率
  const getSizeStyle = (size, pages, aspectRatio) => {
    const baseWidth = 112;
    const baseHeight = 148;
    let width = baseWidth;
    let height = baseHeight;
    if (size === 'コミック') {
      width = 100;
      height = 160;
    } else if (size === 'A5') {
      width = 112;
      height = 148;
    } else if (size === 'B6') {
      width = 105;
      height = 140;
    }
    // aspectRatioが利用可能ならheightを調整
    if (aspectRatio && aspectRatio > 0) {
      height = width / aspectRatio;
    }
    // ページ数で厚みを表現（例: 影のblurを増やす）
    const thickness = Math.min(pages / 200, 1) * 10; // 最大10px
    return { width: `${width}px`, height: `${height}px`, thickness };
  };

  React.useEffect(() => {
    // ジャンル情報を読み込む
    const genrePromises = [
      fetch('001005genre.json').then(response => response.json()),
      fetch('001006genre.json').then(response => response.json()),
      fetch('001010genre.json').then(response => response.json())
    ];
    Promise.all(genrePromises)
      .then(([genre005, genre006, genre010]) => {
        const map = {};
        // 001005genre
        map[genre005.current.booksGenreId] = genre005.current.booksGenreName;
        genre005.children.forEach(child => {
          map[child.child.booksGenreId] = child.child.booksGenreName;
        });
        // 001006genre
        map[genre006.current.booksGenreId] = genre006.current.booksGenreName;
        genre006.children.forEach(child => {
          map[child.child.booksGenreId] = child.child.booksGenreName;
        });
        // 001010genre
        map[genre010.current.booksGenreId] = genre010.current.booksGenreName;
        genre010.children.forEach(child => {
          map[child.child.booksGenreId] = child.child.booksGenreName;
        });
        setGenreMap(map);
      })
      .catch(error => console.error('Error loading genre JSON:', error));
  }, []);

  React.useEffect(() => {
    if (Object.keys(genreMap).length === 0) return; // genreMapが読み込まれるまで待つ

    // 本のデータを読み込む
    const bookPromises = [
      fetch('001005.json').then(response => response.json()),
      fetch('001006.json').then(response => response.json()),
      fetch('001010.json').then(response => response.json())
    ];
    Promise.all(bookPromises)
      .then(([json005, json006, json010]) => {
        const grouped = { tech: [], biz: [], culture: [] };
        const allBooks = [];
        // 001005.json -> tech
        json005.Items.forEach(item => {
          const book = item.Item;
          const bookData = {
            id: book.isbn,
            title: book.title.length > 12 ? book.title.substring(0, 12) + '...' : book.title,
            author: book.author.length > 12 ? book.author.substring(0, 12) + '...' : book.author,
            fullTitle: book.title,
            fullAuthor: book.author,
            coverUrl: book.largeImageUrl,
            url: book.itemUrl,
            isbn: book.isbn,
            shelf: 'tech'
          };
          grouped.tech.push(bookData);
          allBooks.push(bookData);
        });
        // 001006.json -> biz
        json006.Items.forEach(item => {
          const book = item.Item;
          const bookData = {
            id: book.isbn,
            title: book.title.length > 12 ? book.title.substring(0, 12) + '...' : book.title,
            author: book.author.length > 12 ? book.author.substring(0, 12) + '...' : book.author,
            fullTitle: book.title,
            fullAuthor: book.author,
            coverUrl: book.largeImageUrl,
            url: book.itemUrl,
            isbn: book.isbn,
            shelf: 'biz'
          };
          grouped.biz.push(bookData);
          allBooks.push(bookData);
        });
        // 001010.json -> culture
        json010.Items.forEach(item => {
          const book = item.Item;
          const bookData = {
            id: book.isbn,
            title: book.title.length > 12 ? book.title.substring(0, 12) + '...' : book.title,
            author: book.author.length > 12 ? book.author.substring(0, 12) + '...' : book.author,
            fullTitle: book.title,
            fullAuthor: book.author,
            coverUrl: book.largeImageUrl,
            url: book.itemUrl,
            isbn: book.isbn,
            shelf: 'culture'
          };
          grouped.culture.push(bookData);
          allBooks.push(bookData);
        });

        // openBDから追加情報を取得
        const isbns = allBooks.map(b => b.isbn);
        const chunks = chunkArray(isbns, 30);
        const openbdPromises = chunks.map(chunk => 
          fetch(`https://api.openbd.jp/v1/get?isbn=${chunk.join(',')}`).then(response => response.json())
        );
        Promise.all(openbdPromises)
          .then(results => {
            const openbdData = {};
            results.forEach(result => {
              result.forEach(book => {
                if (book && book.summary) {
                  openbdData[book.summary.isbn] = {
                    pages: book.summary.pages,
                    size: book.summary.size
                  };
                }
              });
            });
            // 本のデータに統合
            allBooks.forEach(book => {
              if (openbdData[book.isbn]) {
                book.pages = openbdData[book.isbn].pages;
                book.size = openbdData[book.isbn].size;
                console.log(`ISBN: ${book.isbn}, Size: ${book.size}, Pages: ${book.pages}`);
              }
            });

            // 画像の縦横比を取得
            const imagePromises = allBooks.map(book => {
              return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                  book.aspectRatio = img.naturalWidth / img.naturalHeight;
                  resolve();
                };
                img.onerror = () => {
                  book.aspectRatio = 0.7; // デフォルト縦横比（本の標準）
                  resolve();
                };
                img.src = book.coverUrl;
              });
            });
            Promise.all(imagePromises).then(() => {
              const shelvesData = [
                { id: 'tech', title: 'テクノロジー', books: grouped.tech },
                { id: 'biz', title: 'ビジネス', books: grouped.biz },
                { id: 'culture', title: 'カルチャー', books: grouped.culture }
              ];
              setData(shelvesData);
            });
          })
          .catch(error => {
            console.error('Error loading openBD data:', error);
            // openBDが失敗しても本のデータをセット
            const shelvesData = [
              { id: 'tech', title: 'テクノロジー', books: grouped.tech },
              { id: 'biz', title: 'ビジネス', books: grouped.biz },
              { id: 'culture', title: 'カルチャー', books: grouped.culture }
            ];
            setData(shelvesData);
          });
      })
      .catch(error => console.error('Error loading JSON:', error));
  }, [genreMap]);

  // 配列をチャンクに分ける関数
  function chunkArray(array, chunkSize) {
    const chunks = [];
    for (let i = 0; i < array.length; i += chunkSize) {
      chunks.push(array.slice(i, i + chunkSize));
    }
    return chunks;
  }

  const getGenreTitle = (genreId) => {
    return genreMap[genreId] || 'その他';
  };

  // 配列をシャッフルする関数
  function shuffleArray(array) {
    const shuffled = [...array];
    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
  }

  const prepared = React.useMemo(() => {
    const currentShelves = data || shelves;
    return currentShelves.map((s) => {
      const featured = s.books.slice(0, featuredCount).map(b => ({ ...b, type: 'featured' }));
      let rest = s.books.slice(featuredCount, featuredCount + maxSpines).map(b => ({ ...b, type: 'spine' }));
      // restからランダムにrandomFeaturedCount冊選んでfeaturedに
      if (rest.length > randomFeaturedCount) {
        const shuffledRest = shuffleArray(rest);
        const randomFeatured = shuffledRest.slice(0, randomFeaturedCount).map(b => ({ ...b, type: 'featured' }));
        const remainingSpines = shuffledRest.slice(randomFeaturedCount).map(b => ({ ...b, type: 'spine' }));
        rest = [...randomFeatured, ...remainingSpines];
      }
      const mixedBooks = shuffleArray([...featured, ...rest]);
      return { ...s, mixedBooks };
    });
  }, [data, shelves, featuredCount, maxSpines, randomFeaturedCount]);

  return (
    <div className="uz-wrap">
      <header className="uz-header">
        <div>
          <div className="uz-kicker">UZ / Book UI Prototype</div>
          <h1 className="uz-title">擬似3D 本屋UI</h1>
        </div>
        <div className="uz-note">
          背表紙：色面＋縦書き / 人気：先頭{featuredCount}冊（暫定）
        </div>
      </header>

      <div className="uz-grid">
        {prepared.map((shelf) => (
          <section key={shelf.id} className="uz-shelf">
            <div className="uz-shelfHead">
              <h2 className="uz-shelfTitle">{shelf.title}</h2>
              <div className="uz-shelfMeta">
                {shelf.books.length} items
              </div>
            </div>

            {/* 棚：正面＋背表紙 */}
            <div className="uz-rack">
              {/* 棚板 */}
              <div className="uz-plank" aria-hidden="true" />

              {/* mixed：正面表紙と背表紙をランダムに混ぜて表示 */}
              <div className="uz-mixedRow">
                {shelf.mixedBooks.map((b) => {
                  const sizeStyle = getSizeStyle(b.size, b.pages, b.aspectRatio);
                  if (b.type === 'featured') {
                    return (
                      <a
                        key={b.id}
                        className="uz-bookFace"
                        href={b.url}
                        style={{ width: sizeStyle.width, height: sizeStyle.height }}
                        onMouseEnter={(e) => handleMouseEnter(e, b)}
                        onMouseLeave={handleMouseLeave}
                      >
                        <div
                          className="uz-bookFace__cover"
                          style={{ backgroundImage: `url(${b.coverUrl})` }}
                        />
                        <div className="uz-bookFace__edge" aria-hidden="true" />
                        <div className="uz-bookFace__shadow" aria-hidden="true" style={{ filter: `blur(${sizeStyle.thickness}px)` }} />
                      </a>
                    );
                  } else {
                    return (
                      <a
                        key={b.id}
                        className="uz-spine"
                        href={b.url}
                        style={{
                          backgroundColor: spineColorFromTitle(b.title),
                          width: sizeStyle.thickness,
                        }}
                        onMouseEnter={(e) => handleMouseEnter(e, b)}
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

      {/* ツールチップ */}
      {tooltip && (
        <div
          className="uz-tooltip"
          style={{
            position: 'fixed',
            left: tooltip.x + 10,
            top: tooltip.y + 10,
            backgroundColor: 'rgba(0,0,0,0.8)',
            color: 'white',
            padding: '8px 12px',
            borderRadius: '4px',
            fontSize: '12px',
            pointerEvents: 'none',
            zIndex: 1000,
            maxWidth: '200px',
          }}
        >
          <div style={{ fontWeight: 'bold' }}>{tooltip.book.fullTitle || tooltip.book.title}</div>
          <div>{tooltip.book.fullAuthor || tooltip.book.author}</div>
          {tooltip.book.size && <div>サイズ: {tooltip.book.size}</div>}
          {tooltip.book.pages && <div>ページ数: {tooltip.book.pages}</div>}
        </div>
      )}

    </div>
  );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<Bookshelf3D />);
