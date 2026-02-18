/* ========================================
   Realistic Bookshelf App - JavaScript
   ======================================== */

// ---- Sample Book Data ----
const DEFAULT_BOOKS = [
  {
    id: "b1",
    title: "吾輩は猫である",
    author: "夏目漱石",
    color: "#2c5f2d",
    thickness: "thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "猫の視点から人間社会を風刺的に描いた夏目漱石の処女長編小説。",
    publisher: "岩波書店",
    year: 1905,
    affiliateUrl: "",
    shelf: 0
  },
  {
    id: "b2",
    title: "人間失格",
    author: "太宰治",
    color: "#8b1a1a",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "「恥の多い生涯を送って来ました」で始まる太宰治の代表的自伝的小説。",
    publisher: "新潮社",
    year: 1948,
    affiliateUrl: "",
    shelf: 0
  },
  {
    id: "b3",
    title: "ノルウェイの森",
    author: "村上春樹",
    color: "#1a3a5c",
    thickness: "thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "1960年代の東京を舞台に、喪失と再生を描いた村上春樹の代表作。",
    publisher: "講談社",
    year: 1987,
    affiliateUrl: "",
    shelf: 0
  },
  {
    id: "b4",
    title: "コンビニ人間",
    author: "村田沙耶香",
    color: "#f0e68c",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "コンビニのアルバイトとして生きる女性の物語。芥川賞受賞作。",
    publisher: "文藝春秋",
    year: 2016,
    affiliateUrl: "",
    shelf: 0
  },
  {
    id: "b5",
    title: "火花",
    author: "又吉直樹",
    color: "#ff4500",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "お笑い芸人の友情と葛藤を描いた又吉直樹の芥川賞受賞作。",
    publisher: "文藝春秋",
    year: 2015,
    affiliateUrl: "",
    shelf: 0
  },
  {
    id: "b6",
    title: "1Q84",
    author: "村上春樹",
    color: "#2f4f4f",
    thickness: "very-thick",
    height: "tall",
    display: "spine",
    coverUrl: "",
    description: "1984年のパラレルワールドを舞台にした壮大な物語。",
    publisher: "新潮社",
    year: 2009,
    affiliateUrl: "",
    shelf: 1
  },
  {
    id: "b7",
    title: "雪国",
    author: "川端康成",
    color: "#e8e8e8",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "「国境の長いトンネルを抜けると雪国であった」で始まるノーベル賞作家の代表作。",
    publisher: "岩波書店",
    year: 1937,
    affiliateUrl: "",
    shelf: 1
  },
  {
    id: "b8",
    title: "羅生門",
    author: "芥川龍之介",
    color: "#4a0e0e",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "平安時代末期の羅生門を舞台にした人間のエゴイズムを描く短編。",
    publisher: "角川書店",
    year: 1915,
    affiliateUrl: "",
    shelf: 1
  },
  {
    id: "b9",
    title: "容疑者Xの献身",
    author: "東野圭吾",
    color: "#191970",
    thickness: "thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "天才数学者が隣人のために完全犯罪を企てる直木賞受賞ミステリー。",
    publisher: "文藝春秋",
    year: 2005,
    affiliateUrl: "",
    shelf: 1
  },
  {
    id: "b10",
    title: "キッチン",
    author: "吉本ばなな",
    color: "#dda0dd",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "祖母を亡くした少女が台所に安らぎを見出す、吉本ばななのデビュー作。",
    publisher: "福武書店",
    year: 1988,
    affiliateUrl: "",
    shelf: 1
  },
  {
    id: "b11",
    title: "世界の終りとハードボイルド・ワンダーランド",
    author: "村上春樹",
    color: "#556b2f",
    thickness: "very-thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "二つの世界が交互に語られる村上春樹の初期長編。谷崎潤一郎賞受賞。",
    publisher: "新潮社",
    year: 1985,
    affiliateUrl: "",
    shelf: 2
  },
  {
    id: "b12",
    title: "蜜蜂と遠雷",
    author: "恩田陸",
    color: "#daa520",
    thickness: "very-thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "国際ピアノコンクールを舞台に4人のコンテスタントの物語。直木賞・本屋大賞W受賞。",
    publisher: "幻冬舎",
    year: 2016,
    affiliateUrl: "",
    shelf: 2
  },
  {
    id: "b13",
    title: "博士の愛した数式",
    author: "小川洋子",
    color: "#4682b4",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "80分しか記憶が持たない元数学者と家政婦親子の交流を描く。本屋大賞受賞。",
    publisher: "新潮社",
    year: 2003,
    affiliateUrl: "",
    shelf: 2
  },
  {
    id: "b14",
    title: "銀河鉄道の夜",
    author: "宮沢賢治",
    color: "#0d0d3d",
    thickness: "medium",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "ジョバンニとカムパネルラが銀河鉄道に乗って旅をする幻想的な物語。",
    publisher: "岩波書店",
    year: 1934,
    affiliateUrl: "",
    shelf: 2
  },
  {
    id: "b15",
    title: "風の歌を聴け",
    author: "村上春樹",
    color: "#cd853f",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "村上春樹のデビュー作。1970年の夏の21日間を描いた青春小説。",
    publisher: "講談社",
    year: 1979,
    affiliateUrl: "",
    shelf: 2
  },
  {
    id: "b16",
    title: "源氏物語",
    author: "紫式部",
    color: "#800080",
    thickness: "very-thick",
    height: "tall",
    display: "spine",
    coverUrl: "",
    description: "平安時代中期に書かれた、世界最古の長編小説とされる日本文学の最高傑作。",
    publisher: "岩波書店",
    year: 1008,
    affiliateUrl: "",
    shelf: 3
  },
  {
    id: "b17",
    title: "坊っちゃん",
    author: "夏目漱石",
    color: "#cc5500",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "江戸っ子気質の主人公が四国の中学校で奮闘する痛快小説。",
    publisher: "岩波書店",
    year: 1906,
    affiliateUrl: "",
    shelf: 3
  },
  {
    id: "b18",
    title: "告白",
    author: "湊かなえ",
    color: "#2d2d2d",
    thickness: "medium",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "娘を殺された女性教師の復讐を描くイヤミス。本屋大賞受賞。",
    publisher: "双葉社",
    year: 2008,
    affiliateUrl: "",
    shelf: 3
  },
  {
    id: "b19",
    title: "色彩を持たない多崎つくると、彼の巡礼の年",
    author: "村上春樹",
    color: "#708090",
    thickness: "thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "高校時代の親友グループから突然追放された男の物語。",
    publisher: "文藝春秋",
    year: 2013,
    affiliateUrl: "",
    shelf: 3
  },
  {
    id: "b20",
    title: "コーヒーが冷めないうちに",
    author: "川口俊和",
    color: "#6b4226",
    thickness: "medium",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "過去に戻れる不思議な喫茶店を舞台にした4つの物語。",
    publisher: "サンマーク出版",
    year: 2015,
    affiliateUrl: "",
    shelf: 3
  },
  {
    id: "b21",
    title: "三体",
    author: "劉慈欣",
    color: "#1c1c1c",
    thickness: "very-thick",
    height: "tall",
    display: "spine",
    coverUrl: "",
    description: "中国発の壮大なSF三部作。地球外文明との接触を描く。ヒューゴー賞受賞。",
    publisher: "早川書房",
    year: 2019,
    affiliateUrl: "",
    shelf: 4
  },
  {
    id: "b22",
    title: "思考の整理学",
    author: "外山滋比古",
    color: "#3cb371",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "東大・京大で一番読まれた本。思考法のロングセラー。",
    publisher: "筑摩書房",
    year: 1986,
    affiliateUrl: "",
    shelf: 4
  },
  {
    id: "b23",
    title: "海辺のカフカ",
    author: "村上春樹",
    color: "#006666",
    thickness: "thick",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "15歳の少年カフカが家出をして四国の図書館にたどり着く物語。",
    publisher: "新潮社",
    year: 2002,
    affiliateUrl: "",
    shelf: 4
  },
  {
    id: "b24",
    title: "走れメロス",
    author: "太宰治",
    color: "#b22222",
    thickness: "thin",
    height: "short",
    display: "spine",
    coverUrl: "",
    description: "友情と信頼をテーマにした太宰治の代表的短編小説。",
    publisher: "新潮社",
    year: 1940,
    affiliateUrl: "",
    shelf: 4
  },
  {
    id: "b25",
    title: "窓ぎわのトットちゃん",
    author: "黒柳徹子",
    color: "#ff6699",
    thickness: "medium",
    height: "medium",
    display: "spine",
    coverUrl: "",
    description: "黒柳徹子の自伝的物語。トモエ学園での自由な教育体験を描く。",
    publisher: "講談社",
    year: 1981,
    affiliateUrl: "",
    shelf: 4
  }
];

const NUM_SHELVES = 5;

// ---- State ----
let books = [];
let dragState = null;

// ---- Persistence ----
function loadBooks() {
  const saved = localStorage.getItem("bookshelf_books");
  if (saved) {
    try {
      books = JSON.parse(saved);
      return;
    } catch (e) {
      // fall through to defaults
    }
  }
  books = JSON.parse(JSON.stringify(DEFAULT_BOOKS));
}

function saveBooks() {
  localStorage.setItem("bookshelf_books", JSON.stringify(books));
}

// ---- Utility ----
function generateId() {
  return "b" + Date.now() + Math.random().toString(36).slice(2, 7);
}

function getTextColor(bgColor) {
  // Simple luminance check
  const hex = bgColor.replace("#", "");
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.55 ? "#2c2c2c" : "rgba(255,255,255,0.92)";
}

// ---- Render ----
function render() {
  const bookcase = document.getElementById("bookcase");
  bookcase.innerHTML = "";

  for (let s = 0; s < NUM_SHELVES; s++) {
    const shelfBooks = books.filter(b => b.shelf === s);

    const shelfUnit = document.createElement("div");
    shelfUnit.className = "shelf-unit";

    // Back panel with books
    const shelfBack = document.createElement("div");
    shelfBack.className = "shelf-back";
    shelfBack.dataset.shelf = s;

    const booksRow = document.createElement("div");
    booksRow.className = "books-row";
    booksRow.dataset.shelf = s;

    if (shelfBooks.length === 0) {
      const emptyMsg = document.createElement("div");
      emptyMsg.className = "shelf-empty-msg";
      emptyMsg.textContent = "この棚は空です";
      booksRow.appendChild(emptyMsg);
    } else {
      // Add a bookend at the start if shelf isn't full
      if (shelfBooks.length < 10) {
        const bookend = document.createElement("div");
        bookend.className = "bookend";
        booksRow.appendChild(bookend);
      }

      shelfBooks.forEach((book, index) => {
        const bookEl = createBookElement(book, index, shelfBooks.length);
        booksRow.appendChild(bookEl);
      });
    }

    shelfBack.appendChild(booksRow);
    shelfUnit.appendChild(shelfBack);

    // Shelf board
    const shelfBoard = document.createElement("div");
    shelfBoard.className = "shelf-board";
    shelfUnit.appendChild(shelfBoard);

    bookcase.appendChild(shelfUnit);
  }

  // Bottom of bookcase
  const bottom = document.createElement("div");
  bottom.className = "bookcase-bottom";
  bookcase.appendChild(bottom);

  // Setup drag-and-drop for all book rows
  setupDragAndDrop();
}

function createBookElement(book, index, totalInShelf) {
  const el = document.createElement("div");
  el.className = `book thickness-${book.thickness} height-${book.height}`;
  el.dataset.id = book.id;

  // Slight random lean for realism (only for last book or isolated ones)
  if (index === totalInShelf - 1 && totalInShelf > 1 && Math.random() > 0.6) {
    el.classList.add("leaning-right");
  }

  if (book.display === "cover") {
    // Face-out display
    if (book.coverUrl) {
      const coverDiv = document.createElement("div");
      coverDiv.className = "book-cover-display";
      const img = document.createElement("img");
      img.src = book.coverUrl;
      img.alt = book.title;
      img.draggable = false;
      coverDiv.appendChild(img);
      el.appendChild(coverDiv);
    } else {
      const placeholder = document.createElement("div");
      placeholder.className = "book-cover-placeholder";
      placeholder.style.backgroundColor = book.color;
      placeholder.style.color = getTextColor(book.color);
      placeholder.textContent = book.title;
      el.appendChild(placeholder);
    }
  } else {
    // Spine display
    const spine = document.createElement("div");
    spine.className = "book-spine";
    spine.style.backgroundColor = book.color;
    spine.style.color = getTextColor(book.color);

    const titleSpan = document.createElement("span");
    titleSpan.className = "spine-title";
    titleSpan.textContent = book.title;
    spine.appendChild(titleSpan);

    const authorSpan = document.createElement("span");
    authorSpan.className = "spine-author";
    authorSpan.textContent = book.author;
    spine.appendChild(authorSpan);

    // Publisher mark at bottom
    const mark = document.createElement("div");
    mark.className = "spine-publisher-mark";
    spine.appendChild(mark);

    el.appendChild(spine);
  }

  // Click to open detail
  el.addEventListener("click", (e) => {
    if (dragState && dragState.moved) return;
    openBookModal(book);
  });

  return el;
}

// ---- Book Detail Modal ----
function openBookModal(book) {
  const modal = document.getElementById("bookModal");
  document.getElementById("modalTitle").textContent = book.title;
  document.getElementById("modalAuthor").textContent = book.author;
  document.getElementById("modalDesc").textContent = book.description || "";
  document.getElementById("modalPublisher").textContent = book.publisher || "";
  document.getElementById("modalYear").textContent = book.year ? `${book.year}年` : "";

  const coverImg = document.getElementById("modalCover");
  if (book.coverUrl) {
    coverImg.src = book.coverUrl;
    coverImg.alt = book.title;
    coverImg.style.display = "block";
  } else {
    // Generate a colored placeholder
    coverImg.style.display = "none";
  }

  const affLink = document.getElementById("modalAffLink");
  if (book.affiliateUrl) {
    affLink.href = book.affiliateUrl;
    affLink.style.display = "inline-block";
  } else {
    affLink.style.display = "none";
  }

  modal.classList.add("active");
}

function closeBookModal() {
  document.getElementById("bookModal").classList.remove("active");
}

// ---- Add Book Modal ----
function openAddModal() {
  document.getElementById("addModal").classList.add("active");
}

function closeAddModal() {
  document.getElementById("addModal").classList.remove("active");
  document.getElementById("addBookForm").reset();
}

function handleAddBook(e) {
  e.preventDefault();

  const newBook = {
    id: generateId(),
    title: document.getElementById("bookTitle").value.trim(),
    author: document.getElementById("bookAuthor").value.trim(),
    color: document.getElementById("bookColor").value,
    thickness: document.getElementById("bookThickness").value,
    height: document.getElementById("bookHeight").value,
    display: document.getElementById("bookDisplay").value,
    coverUrl: document.getElementById("bookCover").value.trim(),
    description: document.getElementById("bookDesc").value.trim(),
    publisher: document.getElementById("bookPublisher").value.trim(),
    year: parseInt(document.getElementById("bookYear").value) || null,
    affiliateUrl: document.getElementById("bookAffLink").value.trim(),
    shelf: parseInt(document.getElementById("bookShelf").value)
  };

  books.push(newBook);
  saveBooks();
  render();
  closeAddModal();
}

// ---- Drag and Drop ----
function setupDragAndDrop() {
  const allBooks = document.querySelectorAll(".book");

  allBooks.forEach(bookEl => {
    bookEl.addEventListener("mousedown", onDragStart);
    bookEl.addEventListener("touchstart", onTouchStart, { passive: false });
  });
}

function onTouchStart(e) {
  if (e.touches.length !== 1) return;
  const touch = e.touches[0];
  const bookEl = e.currentTarget;

  dragState = {
    bookId: bookEl.dataset.id,
    startX: touch.clientX,
    startY: touch.clientY,
    moved: false,
    ghost: null,
    isTouch: true
  };

  document.addEventListener("touchmove", onTouchMove, { passive: false });
  document.addEventListener("touchend", onTouchEnd);
}

function onTouchMove(e) {
  if (!dragState) return;
  e.preventDefault();
  const touch = e.touches[0];
  const dx = touch.clientX - dragState.startX;
  const dy = touch.clientY - dragState.startY;

  if (!dragState.moved && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
    dragState.moved = true;
    startGhostDrag(dragState.bookId, touch.clientX, touch.clientY);
  }

  if (dragState.moved && dragState.ghost) {
    moveGhost(touch.clientX, touch.clientY);
    updateDropIndicator(touch.clientX, touch.clientY);
  }
}

function onTouchEnd(e) {
  document.removeEventListener("touchmove", onTouchMove);
  document.removeEventListener("touchend", onTouchEnd);

  if (dragState && dragState.moved) {
    const touch = e.changedTouches[0];
    finishDrop(touch.clientX, touch.clientY);
  }

  cleanupDrag();
}

function onDragStart(e) {
  if (e.button !== 0) return;
  const bookEl = e.currentTarget;

  dragState = {
    bookId: bookEl.dataset.id,
    startX: e.clientX,
    startY: e.clientY,
    moved: false,
    ghost: null,
    isTouch: false
  };

  document.addEventListener("mousemove", onDragMove);
  document.addEventListener("mouseup", onDragEnd);
}

function onDragMove(e) {
  if (!dragState) return;
  const dx = e.clientX - dragState.startX;
  const dy = e.clientY - dragState.startY;

  if (!dragState.moved && (Math.abs(dx) > 5 || Math.abs(dy) > 5)) {
    dragState.moved = true;
    startGhostDrag(dragState.bookId, e.clientX, e.clientY);
  }

  if (dragState.moved && dragState.ghost) {
    moveGhost(e.clientX, e.clientY);
    updateDropIndicator(e.clientX, e.clientY);
  }
}

function onDragEnd(e) {
  document.removeEventListener("mousemove", onDragMove);
  document.removeEventListener("mouseup", onDragEnd);

  if (dragState && dragState.moved) {
    finishDrop(e.clientX, e.clientY);
  }

  cleanupDrag();
}

function startGhostDrag(bookId, x, y) {
  const original = document.querySelector(`.book[data-id="${bookId}"]`);
  if (!original) return;

  original.classList.add("dragging");

  // Create ghost
  const ghost = original.cloneNode(true);
  ghost.classList.remove("dragging");
  ghost.classList.add("book-ghost");
  ghost.style.width = original.offsetWidth + "px";
  ghost.style.height = original.offsetHeight + "px";
  ghost.style.left = (x - original.offsetWidth / 2) + "px";
  ghost.style.top = (y - original.offsetHeight / 2) + "px";
  document.body.appendChild(ghost);

  dragState.ghost = ghost;
  dragState.ghostOffsetX = original.offsetWidth / 2;
  dragState.ghostOffsetY = original.offsetHeight / 2;
}

function moveGhost(x, y) {
  if (!dragState || !dragState.ghost) return;
  dragState.ghost.style.left = (x - dragState.ghostOffsetX) + "px";
  dragState.ghost.style.top = (y - dragState.ghostOffsetY) + "px";
}

function updateDropIndicator(x, y) {
  // Remove old indicators
  document.querySelectorAll(".drop-indicator").forEach(el => el.remove());

  const target = findDropTarget(x, y);
  if (!target) return;

  const indicator = document.createElement("div");
  indicator.className = "drop-indicator";

  if (target.insertBefore) {
    target.row.insertBefore(indicator, target.insertBefore);
  } else {
    target.row.appendChild(indicator);
  }
}

function findDropTarget(x, y) {
  const rows = document.querySelectorAll(".books-row");
  for (const row of rows) {
    const rect = row.getBoundingClientRect();
    if (y >= rect.top - 20 && y <= rect.bottom + 20) {
      const shelf = parseInt(row.dataset.shelf);
      const bookEls = row.querySelectorAll(".book");

      if (bookEls.length === 0) {
        return { row, shelf, index: 0, insertBefore: null };
      }

      for (let i = 0; i < bookEls.length; i++) {
        const br = bookEls[i].getBoundingClientRect();
        const midX = br.left + br.width / 2;
        if (x < midX) {
          return { row, shelf, index: i, insertBefore: bookEls[i] };
        }
      }

      return { row, shelf, index: bookEls.length, insertBefore: null };
    }
  }
  return null;
}

function finishDrop(x, y) {
  const target = findDropTarget(x, y);
  if (!target || !dragState) return;

  const bookId = dragState.bookId;
  const book = books.find(b => b.id === bookId);
  if (!book) return;

  // Remove from old position
  const oldShelf = book.shelf;
  const shelfBooks = books.filter(b => b.shelf === oldShelf && b.id !== bookId);
  const otherBooks = books.filter(b => b.shelf !== oldShelf);

  // Calculate new position
  const targetShelfBooks = target.shelf === oldShelf
    ? shelfBooks
    : books.filter(b => b.shelf === target.shelf);

  book.shelf = target.shelf;

  // Rebuild books array maintaining order
  const newBooks = [];
  for (let s = 0; s < NUM_SHELVES; s++) {
    let sb = books.filter(b => b.shelf === s && b.id !== bookId);
    if (s === target.shelf) {
      sb.splice(target.index, 0, book);
    }
    newBooks.push(...sb);
  }

  books = newBooks;
  saveBooks();
  render();
}

function cleanupDrag() {
  if (dragState && dragState.ghost) {
    dragState.ghost.remove();
  }
  document.querySelectorAll(".dragging").forEach(el => el.classList.remove("dragging"));
  document.querySelectorAll(".drop-indicator").forEach(el => el.remove());
  dragState = null;
}

// ---- Toggle View (reset to defaults) ----
function toggleView() {
  if (confirm("サンプルデータにリセットしますか？追加した本は消えます。")) {
    localStorage.removeItem("bookshelf_books");
    loadBooks();
    render();
  }
}

// ---- Event Listeners ----
document.getElementById("addBookBtn").addEventListener("click", openAddModal);
document.getElementById("toggleViewBtn").addEventListener("click", toggleView);
document.getElementById("modalClose").addEventListener("click", closeBookModal);
document.getElementById("addModalClose").addEventListener("click", closeAddModal);
document.getElementById("addBookForm").addEventListener("submit", handleAddBook);

// Close modals on overlay click
document.getElementById("bookModal").addEventListener("click", (e) => {
  if (e.target === e.currentTarget) closeBookModal();
});
document.getElementById("addModal").addEventListener("click", (e) => {
  if (e.target === e.currentTarget) closeAddModal();
});

// Close on Escape
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeBookModal();
    closeAddModal();
  }
});

// ---- Init ----
loadBooks();
render();
