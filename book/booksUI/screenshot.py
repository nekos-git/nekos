#!/usr/bin/env python3
"""Take feature-by-feature screenshots of UZ Bookshelf UI."""
import os

CHROME = "/root/.cache/ms-playwright/chromium-1194/chrome-linux/chrome"
BASE_URL = "http://localhost:8765/index_screenshot.html"
OUT_DIR = "/home/user/nekos/book/booksUI/screenshots"
os.makedirs(OUT_DIR, exist_ok=True)

def take_screenshots():
    from playwright.sync_api import sync_playwright

    with sync_playwright() as p:
        browser = p.chromium.launch(
            executable_path=CHROME, headless=True,
            args=["--no-sandbox", "--disable-gpu"]
        )
        page = browser.new_page(
            viewport={"width": 1280, "height": 900},
            device_scale_factor=2,
        )

        print("Loading page...")
        page.goto(BASE_URL, wait_until="domcontentloaded", timeout=60000)
        page.wait_for_timeout(12000)

        def js_click(selector, index=0):
            page.evaluate(f'document.querySelectorAll("{selector}")[{index}].click()')

        def close_modal():
            page.evaluate('''
                const overlay = document.querySelector(".uz-modalOverlay");
                if (overlay) overlay.click();
            ''')
            page.wait_for_timeout(500)
            # Double check
            page.evaluate('document.querySelector(".uz-modal__close")?.click()')
            page.wait_for_timeout(300)

        def click_tab(text):
            page.evaluate(f'''
                document.querySelectorAll(".uz-tabBtn").forEach(b => {{
                    if (b.textContent.includes("{text}")) b.click();
                }});
            ''')
            page.wait_for_timeout(500)

        # ===== 1. UZ Selection Main =====
        print("1. UZ Selection - メインビュー")
        page.screenshot(path=f"{OUT_DIR}/01_uz_selection_main.png", full_page=False)

        # ===== 2. Full page =====
        print("2. UZ Selection - 全棚一覧")
        page.screenshot(path=f"{OUT_DIR}/02_uz_selection_fullpage.png", full_page=True)

        # ===== 3. Tooltip =====
        print("3. ツールチップ表示")
        spines = page.query_selector_all(".uz-spine2")
        if len(spines) > 5:
            spines[5].hover()
            page.wait_for_timeout(800)
            page.screenshot(path=f"{OUT_DIR}/03_tooltip.png", full_page=False)
        page.mouse.move(10, 10)
        page.wait_for_timeout(300)

        # ===== 4. Modal (UZ selection) =====
        print("4. 書籍詳細モーダル")
        js_click(".uz-spine2", 2)
        page.wait_for_timeout(800)
        page.screenshot(path=f"{OUT_DIR}/04_modal_uz.png", full_page=False)
        close_modal()

        # ===== 5. Article list =====
        print("5. 記事一覧パネル")
        click_tab("記事一覧")
        page.wait_for_timeout(800)
        page.screenshot(path=f"{OUT_DIR}/05_articles_panel.png", full_page=False)

        # ===== 6. Article search =====
        print("6. 記事検索（村上春樹）")
        search = page.query_selector(".uz-searchInput")
        if search:
            search.focus()
            search.fill("村上春樹")
            page.wait_for_timeout(500)
            page.screenshot(path=f"{OUT_DIR}/06_articles_search.png", full_page=False)

            # Clear search properly using keyboard
            search.focus()
            page.keyboard.press("Control+a")
            page.keyboard.press("Backspace")
            page.wait_for_timeout(500)

        # ===== 7. Category filter (映画) with clean state =====
        print("7. カテゴリフィルタ（映画・DVD）")
        page.evaluate('''
            document.querySelectorAll(".uz-filterBtn").forEach(b => {
                if (b.textContent.includes("映画")) b.click();
            });
        ''')
        page.wait_for_timeout(600)
        page.screenshot(path=f"{OUT_DIR}/07_filter_film.png", full_page=False)

        # Reset
        page.evaluate('''
            document.querySelectorAll(".uz-filterBtn").forEach(b => {
                if (b.textContent === "すべて") b.click();
            });
        ''')
        page.wait_for_timeout(300)

        # Close articles
        click_tab("記事一覧")
        page.wait_for_timeout(400)

        # ===== 8. Rakuten Books =====
        print("8. 楽天Booksタブ")
        click_tab("楽天Books")
        # Wait longer for the full chain: genres -> books -> openBD(fail) -> images -> render
        page.wait_for_timeout(15000)

        # Check if data loaded
        shelf_count = page.evaluate('document.querySelectorAll(".uz-shelf").length')
        print(f"   Rakuten shelves found: {shelf_count}")

        page.screenshot(path=f"{OUT_DIR}/08_rakuten_books.png", full_page=False)

        # ===== 9. Rakuten full page =====
        print("9. 楽天Books - 全棚")
        page.screenshot(path=f"{OUT_DIR}/09_rakuten_fullpage.png", full_page=True)

        # ===== 10. Rakuten modal =====
        print("10. 楽天書籍モーダル")
        rakuten_count = page.evaluate('document.querySelectorAll(".uz-book, .uz-spine2").length')
        print(f"   Rakuten items found: {rakuten_count}")
        if rakuten_count > 0:
            js_click(".uz-spine2", 0)
            page.wait_for_timeout(800)
            modal = page.query_selector(".uz-modal")
            if modal:
                page.screenshot(path=f"{OUT_DIR}/10_rakuten_modal.png", full_page=False)
                close_modal()
        else:
            print("   (Rakuten data not loaded - external API unreachable in this env)")

        browser.close()

    print(f"\nAll screenshots saved to {OUT_DIR}/")
    for f in sorted(os.listdir(OUT_DIR)):
        if f.endswith('.png'):
            size = os.path.getsize(f"{OUT_DIR}/{f}")
            print(f"  {f} ({size//1024}KB)")

if __name__ == '__main__':
    take_screenshots()
