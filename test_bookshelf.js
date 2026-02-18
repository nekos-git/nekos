const { JSDOM } = require('jsdom');
const fs = require('fs');

const html = fs.readFileSync('/home/user/nekos/index.html', 'utf8');
const js = fs.readFileSync('/home/user/nekos/app.js', 'utf8');

const dom = new JSDOM(html, {
  runScripts: 'dangerously',
  resources: 'usable',
  pretendToBeVisual: true,
  url: 'http://localhost:8080'
});

const { document } = dom.window;

// Expose let-scoped vars to window for testing
const testJs = js + '\nwindow._books = books;\nwindow._render = render;\nwindow._getTextColor = getTextColor;\n';
dom.window.eval(testJs);

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) { passed++; console.log('  PASS:', msg); }
  else { failed++; console.log('  FAIL:', msg); }
}

console.log('=== Bookshelf App Tests ===\n');

console.log('[Render]');
const shelves = document.querySelectorAll('.shelf-unit');
assert(shelves.length === 5, '5段の棚が生成される');

const bookEls = document.querySelectorAll('.book');
assert(bookEls.length === 25, '25冊の本が表示される');

const shelf0Books = document.querySelectorAll('.books-row[data-shelf="0"] .book');
assert(shelf0Books.length === 5, '1段目に5冊');

const shelf4Books = document.querySelectorAll('.books-row[data-shelf="4"] .book');
assert(shelf4Books.length === 5, '5段目に5冊');

console.log('\n[Book Elements]');
const spine = document.querySelector('.book-spine');
assert(spine != null, '背表紙要素が存在する');

const spineTitle = document.querySelector('.spine-title');
assert(spineTitle && spineTitle.textContent.length > 0, '背表紙にタイトルがある');

const spineAuthor = document.querySelector('.spine-author');
assert(spineAuthor && spineAuthor.textContent.length > 0, '背表紙に著者名がある');

const publisherMark = document.querySelector('.spine-publisher-mark');
assert(publisherMark != null, '出版社マークがある');

console.log('\n[Bookcase Structure]');
const shelfBoards = document.querySelectorAll('.shelf-board');
assert(shelfBoards.length === 5, '棚板が5つある');

const bottom = document.querySelector('.bookcase-bottom');
assert(bottom != null, '本棚の底板がある');

const bookends = document.querySelectorAll('.bookend');
assert(bookends.length > 0, 'ブックエンドが表示される');

const shelfBacks = document.querySelectorAll('.shelf-back');
assert(shelfBacks.length === 5, '棚の背面パネルが5つある');

console.log('\n[Modals]');
const bookModal = document.getElementById('bookModal');
assert(bookModal != null, '詳細モーダルが存在する');
assert(!bookModal.classList.contains('active'), '初期状態でモーダルは非表示');

const addModal = document.getElementById('addModal');
assert(addModal != null, '追加モーダルが存在する');

const affLink = document.getElementById('modalAffLink');
assert(affLink != null, 'アフィリエイトリンクが存在する');
assert(affLink.textContent.includes('Amazon'), 'アフィリエイトボタンにAmazonテキスト');

const addForm = document.getElementById('addBookForm');
assert(addForm != null, '追加フォームが存在する');

console.log('\n[Form Fields]');
const formFields = ['bookTitle','bookAuthor','bookCover','bookColor','bookThickness',
                    'bookHeight','bookDisplay','bookDesc','bookPublisher','bookYear',
                    'bookAffLink','bookShelf'];
formFields.forEach(id => {
  assert(document.getElementById(id) != null, 'フォーム: ' + id + 'が存在する');
});

console.log('\n[Data Integrity]');
const books = dom.window._books;
assert(Array.isArray(books) && books.length === 25, 'booksデータに25冊ある');
assert(books[0].title === '吾輩は猫である', '1冊目は吾輩は猫である');
assert(books.every(b => b.id && b.title && b.author), '全冊にid,title,authorがある');
assert(books.every(b => ['thin','medium','thick','very-thick'].includes(b.thickness)), '厚さが正しい値');
assert(books.every(b => ['short','medium','tall'].includes(b.height)), '高さが正しい値');
assert(books.every(b => ['spine','cover'].includes(b.display)), '表示方法が正しい値');

// Check shelf distribution
const shelfCounts = {};
books.forEach(b => { shelfCounts[b.shelf] = (shelfCounts[b.shelf] || 0) + 1; });
assert(Object.keys(shelfCounts).length === 5, '5段全てに本がある');

console.log('\n[Utility Functions]');
const getTextColor = dom.window._getTextColor;
const whiteText = getTextColor('#000000');
assert(whiteText.includes('255') || whiteText.includes('fff'), '暗い背景 → 白い文字');
const darkText = getTextColor('#ffffff');
assert(darkText.includes('2c'), '明るい背景 → 暗い文字');
const midText = getTextColor('#808080');
assert(typeof midText === 'string' && midText.length > 0, '中間色でも文字色を返す');

console.log('\n[Add Book Simulation]');
books.push({
  id: 'test1', title: 'テスト本', author: 'テスト著者',
  color: '#ff0000', thickness: 'medium', height: 'medium',
  display: 'spine', coverUrl: '', description: 'テスト説明',
  publisher: 'テスト出版社', year: 2024, affiliateUrl: 'https://example.com', shelf: 0
});
dom.window._render();
const newBookEls = document.querySelectorAll('.book');
assert(newBookEls.length === 26, '本を追加 → 26冊に増える');

const testBook = document.querySelector('.book[data-id="test1"]');
assert(testBook != null, '追加した本がDOMに存在する');

// Cover display test
books.push({
  id: 'test2', title: 'カバーテスト', author: 'テスト',
  color: '#0000ff', thickness: 'medium', height: 'medium',
  display: 'cover', coverUrl: '', description: '',
  publisher: '', year: 2024, affiliateUrl: '', shelf: 1
});
dom.window._render();
const coverPlaceholder = document.querySelector('.book-cover-placeholder');
assert(coverPlaceholder != null, '表紙URLなしでプレースホルダー表示');

console.log('\n[Shelf Distribution]');
for (let s = 0; s < 5; s++) {
  const count = document.querySelectorAll('.books-row[data-shelf="' + s + '"] .book').length;
  console.log('  INFO: 棚' + (s+1) + '段目: ' + count + '冊');
}

console.log('\n========================');
console.log('Results: ' + passed + ' passed, ' + failed + ' failed');
if (failed > 0) process.exit(1);
