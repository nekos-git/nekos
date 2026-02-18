<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <title>END2ENDWORLD | 音楽を小説に! 音楽から閃きを得たabejunichiの小説サイト(とエッセイ)</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="keywords" content="小説,短編,音楽,電子書籍,abejunichi,文学,日本文学,エッセイ,短編小説,現代文学,音楽小説,Radiohead,初音ミク">
    <meta name="description" content="音楽を小説に! 音楽から閃きを得たabejunichiの小説サイト(とエッセイ)" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0,minimum-scale=1.0, maximum-scale=1.0,user-scalable=no">
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/style.css">
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:site" content="@junichi_abe" />
    <meta property="og:url" content="https://end2endworld.org" />
    <meta property="og:title" content="END2ENDWORLD" />
    <meta property="og:description" content="音楽を小説に! 音楽から閃きを得たabejunichiの小説サイト(とエッセイ)" />
    <meta property="og:image" content="<?php echo get_template_directory_uri(); ?>/img/twitter_card.jpg" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <?php wp_head(); ?>
    <!-- 構造化データ (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "END2ENDWORLD",
        "url": "<?php echo home_url(); ?>",
        "description": "音楽から閃きを得たabejunichiの小説サイト(とエッセイ)。KID A、Visions、Bird1などの電子書籍を発表している文筆家の公式サイトです。",
        "author": {
            "@type": "Person",
            "name": "abejunichi",
            "url": "<?php echo home_url(); ?>",
            "sameAs": [
                "https://twitter.com/junichi_abe"
            ]
        },
        "publisher": {
            "@type": "Organization",
            "name": "END2ENDWORLD",
            "url": "<?php echo home_url(); ?>"
        },
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "<?php echo home_url(); ?>/?s={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-Z8852YGG4P"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-Z8852YGG4P');
    </script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7699188118204100"
     crossorigin="anonymous"></script>
	    <style>
        /* CSSリセット */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: #f9f9f9;
            line-height: 1.6;
            text-align: center;
        }

        /* ヘッダー */
        .header-outer {
            background-color: #fff;
            border-bottom: 1px solid #e5e5e5;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .site-logo {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .main-nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 25px;
        }

        .nav-menu a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-menu a:hover {
            color: #007BFF;
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: #007BFF;
            transition: width 0.3s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .search-input {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            width: 150px;
            transition: width 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #007BFF;
            width: 200px;
        }

        .search-button {
            background: #007BFF;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-button:hover {
            background: #0056b3;
        }

        .header-text {
            color: #333;
            font-size: 0.9rem;
            margin-left: auto;
        }

        /* モバイルメニュー */
        @media (max-width: 768px) {
            .main-nav {
                position: fixed;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }

            .main-nav.active {
                transform: translateY(0);
            }

            .nav-menu {
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }

            .search-form {
                margin-top: 15px;
                justify-content: center;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .search-input {
                width: 200px;
            }

            .search-input:focus {
                width: 250px;
            }
        }

        /* メインビジュアル */
        .main {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 70vh;
            background-image: url("<?php echo get_template_directory_uri(); ?>/img/top-background.jpg");
            background-size: cover;
            background-position: center;
            color: #fff;
            position: relative;
        }

        .main::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .main-title {
            position: relative;
            z-index: 1;
            padding: 20px;
            border-radius: 10px;
        }

        .main-title h1 {
            font-size: 4rem;
            margin-bottom: 10px;
        }

        .main-title p {
            font-size: 1.5rem;
        }

        /* セクション */
        section {
            padding: 60px 20px;
            margin: 20px 0;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .block-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .block-header h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .block-header h1::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: #007BFF;
            margin: 10px auto 0;
        }

        .block-E-BOOKS, .block-essay, .smn-wrapper, .block1 {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .e-books, .essay, .smn {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px;
            flex: 1 1 calc(30% - 40px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
        }

        .e-books:hover, .essay:hover, .smn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .e-books img, .essay img, .smn img {
            max-width: 100%;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .e-books h1, .essay h2, .smn h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #333;
        }

        .e-books p, .essay p, .smn p {
            font-size: 1rem;
            color: #666;
        }

        /* ボタン */
        .btn, .amazon-btn, .mail, .read {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            border: 2px solid #333;
            border-radius: 50px;
            background: transparent;
            color: #333;
            text-decoration: none;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .btn:hover, .amazon-btn:hover, .mail:hover, .read:hover {
            background: #333;
            color: #fff;
        }

        /* フッター */
        .footer {
            text-align: center;
            padding: 20px;
            background: #fff;
            border-top: 1px solid #e5e5e5;
            margin-top: 40px;
        }

        .footer-text {
            font-size: 0.9rem;
            color: #999;
        }
			
			    /* エッセイセクション用スタイル */
    .essay-item h2 {
      font-size: 1.5rem;
      margin-bottom: 10px;
      color: #333;
    }
    .essay-item a {
      text-decoration: none;
      color: #333;
      transition: color 0.3s ease;
    }
    .essay-item a:hover {
      color: #007BFF;
    }
			
			.block-essay {
    display: block!important;
}

        /* メディアクエリ - 改善版 */
        @media (max-width: 1200px) {
            section {
                margin: 20px 10px;
                padding: 40px 15px;
            }
        }

        @media (max-width: 1024px) {
            .main-title h1 {
                font-size: 3rem;
            }

            .main-title p {
                font-size: 1.2rem;
            }

            .e-books, .essay, .smn {
                flex: 1 1 calc(45% - 20px);
                margin: 10px;
            }

            .header-inner {
                padding: 0 15px;
            }
        }

        @media (max-width: 768px) {
            .main {
                height: 60vh;
            }

            .main-title h1 {
                font-size: 2.5rem;
            }

            .main-title p {
                font-size: 1rem;
                line-height: 1.4;
            }

            .e-books, .essay, .smn {
                flex: 1 1 calc(100% - 20px);
                margin: 10px;
            }

            section {
                padding: 30px 15px;
            }

            .block-header h1 {
                font-size: 2rem;
            }

            .header-text {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .main {
                height: 50vh;
            }

            .main-title h1 {
                font-size: 2rem;
            }

            .main-title p {
                font-size: 0.9rem;
                padding: 0 10px;
            }

            section {
                padding: 20px 10px;
                margin: 10px 5px;
            }

            .block-header h1 {
                font-size: 1.8rem;
            }

            .btn, .amazon-btn, .mail, .read {
                padding: 8px 16px;
                font-size: 0.9rem;
            }

            .header-text {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 360px) {
            .main-title h1 {
                font-size: 1.8rem;
            }

            .main-title p {
                font-size: 0.8rem;
            }

            .block-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="header-outer">
            <div class="header-inner">
                <a href="<?php echo home_url(); ?>" class="site-logo">END2ENDWORLD</a>
                
                <nav class="main-nav" id="main-nav">
                    <ul class="nav-menu">
                        <li><a href="#e-books">電子書籍</a></li>
                        <li><a href="#author">著者紹介</a></li>
                        <li><a href="#essays">エッセイ</a></li>
                        <li><a href="#short-stories">短編小説</a></li>
                    </ul>
                    
                    <form class="search-form" role="search" method="get" action="<?php echo home_url('/'); ?>">
                        <input type="search" class="search-input" placeholder="検索..." value="<?php echo get_search_query(); ?>" name="s" />
                        <button type="submit" class="search-button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </button>
                    </form>
                </nav>
                
                <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                    ☰
                </button>
                
                <div class="header-text">
                    <p>abejunichi@icloud.com</p>
                </div>
            </div>
        </div>
    </header>

    <div class="main">
        <div class="main-title">
            <h1>END2ENDWORLD</h1>
            <p>音楽から閃きを得た<br>abejunichiの小説サイト(とエッセイ)</p>
        </div>
    </div>

   

    <section class="block1 clearfix" id="e-books">
        <div class="block-header">
            <h1>E-BOOKS</h1>
			<p>音楽を閃きにした電子書籍</p>
        </div>
        <div class="block-E-BOOKS" class="block0 clearfix">
            <div class="e-books">
                <a href="https://www.amazon.co.jp/Visions-abejunichi-novel-collection/dp/B0BRH5B4Q7/ref=sr_1_6?dib=eyJ2IjoiMSJ9.IbyJokMjAR0b6dw52PzNd6mECUoK2GNmgW_aAGlHOPxYGwyOIg6cumiC1iAg_cRbDUJmG4pLvz00y9CHy4-GBeyKfR51kQLO_y4K3YKEvpo.iN3qLcI4x2eXOCwR-BE_RsTX8w2yxo9XJmxlTB9rt3Y&dib_tag=se&qid=1710595070&refinements=p_27%3Aabejunichi&s=books&sr=1-6">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/visions.jpg" alt="Visions">
                </a>
                <div class="e-books-title">
                    <h1>Visions abejunichi novel collection</h1>
                    <p>2023</p>
                </div>
                <div class="e-books-text">
                    <p>音楽から閃きをえてWeb、電子書籍を中心に小説を発表してきた著者の選集。処女作「C」、Radioheadの同名アルバムから影響を受けた21世紀の寓話「KID A」、恋愛小説の趣がある「We should fall in love」や「Symphonic Love」、青春音楽恋愛小説「Bird1」と、未来を予言するSF「United Future Organization」の全6作品を収録。</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/Visions-abejunichi-novel-collection/dp/B0BRH5B4Q7/ref=sr_1_6?dib=eyJ2IjoiMSJ9.IbyJokMjAR0b6dw52PzNd6mECUoK2GNmgW_aAGlHOPxYGwyOIg6cumiC1iAg_cRbDUJmG4pLvz00y9CHy4-GBeyKfR51kQLO_y4K3YKEvpo.iN3qLcI4x2eXOCwR-BE_RsTX8w2yxo9XJmxlTB9rt3Y&dib_tag=se&qid=1710595070&refinements=p_27%3Aabejunichi&s=books&sr=1-6">Amazon</a>
					                </div>
				</div>
		
            <div class="e-books">
                <a href="https://www.amazon.co.jp/gp/product/B0B3XBPL1N/ref=dbs_a_def_rwt_bibl_vppi_i0">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/ufo.png" alt="United Future Organization">
                </a>
                <div class="e-books-title">
                    <h1>United Future Organization</h1>
                    <p>2022</p>
                </div>
                <div class="e-books-text">
                    <p>ここで世界が終わり、新しい世界がはじまる。上級開発者が暗号化されたデータを残して失踪した。情報管理者は暗号化の解除を行いながら"K"の行方を追う。AIによって管理された近未来で繰り広げられる情報戦の果てに明らかになった真実とは？ 予言するSF『United Future Organization』始動。</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/gp/product/B0B3XBPL1N/ref=dbs_a_def_rwt_bibl_vppi_i0">Amazon</a>
						</div>
										                <div class="btn">
                    <a href="https://end2endworld.org/archives/973">Read</a>
	</div>
                </div>

            <div class="e-books">
                <a href="https://www.amazon.co.jp/gp/product/B0B37PH122/ref=dbs_a_def_rwt_bibl_vppi_i1">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/bird1.jpg" alt="Bird1">
                </a>
                <div class="e-books-title">
                    <h1>Bird1</h1>
                    <p>2021</p>
                </div>
                <div class="e-books-text">
                    <p>真夜中に鍵盤を弾き、音楽をつくる。たったひとりで。それが大学に行くまでの僕の日常だった。しかしバンドサークルに所属し、バンド活動を開始することで少しずつ暗闇に沈んでいた心は次第に晴れて...。著者自身の体験を元にした魂を揺さぶる青春音楽恋愛小説。そして鳥は大空へと飛ぶのか?</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/gp/product/B0B37PH122/ref=dbs_a_def_rwt_bibl_vppi_i1">Amazon</a>
                </div>
									                <div class="btn">
                    <a href="https://end2endworld.org/archives/975">Read</a>
	</div>
            </div>
            </div>

    
            <div class="e-books">
                <a href="https://www.amazon.co.jp/KID-AbeJunichi-ebook/dp/B00GV4Z1IU/ref=sr_1_1?qid=1558500126&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-1">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/kida.jpg" alt="KID A">
                </a>
                <div class="e-books-title">
                    <h1>KID A</h1>
                    <p>2013</p>
                </div>
                <div class="e-books-text">
                    <p>神や仏の真実に疑問を持つ少年は、髪の長い少年と出会い、世界の中心へ旅立つと予言されるが…。目指すべき者を目指し故郷を離れた少年が目にする世界の真実とは何か？ 『オズの魔法使い』など数々の名作のオマージュに溢れる21世紀の寓話。</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/KID-AbeJunichi-ebook/dp/B00GV4Z1IU/ref=sr_1_1?qid=1558500126&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-1">Amazon</a>
						</div>
										                <div class="btn">
                    <a href="https://end2endworld.org/archives/977">Read</a>
	</div>
   
                </div>
            <div class="e-books">
                <a href="https://www.amazon.co.jp/Symphonic-Love-AbeJunichi-ebook/dp/B0130MAF7M/ref=sr_1_3?qid=1558500154&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-3&text=AbeJunichi">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/symphoniclove.jpg" alt="Symphonic Love">
                </a>
                <div class="e-books-title">
                    <h1>Symphonic Love</h1>
                    <p>2015</p>
                </div>
                <div class="e-books-text">
                    <p>25歳の誕生日に何かが起きる。そう信じている青年の不思議な出会いを描く「We should fall in love」は21世紀のボーイ・ミーツ・ガールだ。また「Symphonic Love」は グレン・グールドに憧れるピアニストが見つけた新しい世界の予兆の音を感じさせるストーリー。それぞれの物語を通じて浮かび上がる真実の愛とは。abejunichiが描く2作目の小説。</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/Symphonic-Love-AbeJunichi-ebook/dp/B0130MAF7M/ref=sr_1_3?qid=1558500154&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-3&text=AbeJunichi">Amazon</a>
                </div>
            </div>
            <div class="e-books">
                <a href="https://www.amazon.co.jp/awake-AbeJunichi-ebook/dp/B06XCYKBKZ/ref=sr_1_2?qid=1558500166&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-2&text=AbeJunichi">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/awake.jpg" alt="awake">
                </a>
                <div class="e-books-title">
                    <h1>awake</h1>
                    <p>2017</p>
                </div>
                <div class="e-books-text">
                    <p>僕たちはどこから来て、どこへ行くのか？ アメリカへ行くことを夢みていた僕は、妻とともにサンフランシスコやシリコンバレーをめぐる旅に出る。ルーツと未来を探るために。現実と幻想が交差する超越的旅行小説。そして何かが目覚める…。abejunichiが描く3作目の小説。</p>
                </div>
                <div class="btn">
                    <a href="https://www.amazon.co.jp/awake-AbeJunichi-ebook/dp/B06XCYKBKZ/ref=sr_1_2?qid=1558500166&refinements=p_27%3AAbeJunichi&s=digital-text&sr=1-2&text=AbeJunichi">Amazon</a>
                </div>
        </div>
    </section>
    

    <section class="block2" id="author">
        <div class="block-header">
            <h1>abejunichi</h1>
            <p>author</p>
        </div>
        <div class="block-author">
  
            <div class="author-text">
                <p>文筆家。 小説などを執筆。 主な作品は「KID A」「We should fall in love」「Symphonic Love」など。音楽を閃きに多様な小説を執筆。初の電子書籍となる「KID A」は、Radioheadの同名アルバムへのオマージュとして、8年間の構想の後、執筆された。また「We should fall in love」と「Symphonic Love」のふたつの短編をまとめた「Symphonic Love」を2015年に発表。2017年に自身の体験と想像をもとに人類の過去と未来を軸にした超越的旅行小説「awake」を発表。2022年7月に新作「Bird1」「United Future Organization」を同時電子書籍化。</p>
            </div>
            <div class="btn mail">
                <a href="mailto:abejunichi@icloud.com">mail</a>
            </div>
        </div>
    </section>

    <!-- <section class="block3">
        <div class="block-header">
            <h1>初期作品</h1>
        </div>
        <div class="block-iw">
            <div class="e-books">
                <div class="title">
                    <h1>C</h1>
                    <p>2005</p>
                    <div class="text">
                        <p>彼女と言っても彼女は彼女ではない。"C"が意味するものは何か？ 2000年代の映し出す連作短編。最後に導き出した答えは？ abejunichiの処女作品。</p>
                    </div>
                    <div class="btn">
                        <a href="https://end2endworld.com/?page_id=25">Read</a>
                    </div>
                </div>
            </div>
            <div class="e-books">
                <div class="title">
                    <h1>コンフリクト</h1>
                    <p>2013</p>
                    <div class="text">
                        <p>夜明け前の静けさと突然の破壊。シャープなショート・ストーリー。</p>
                    </div>
                    <div class="btn">
                        <a href="https://end2endworld.com/?page_id=31">Read</a>
                    </div>
                </div>
            </div>
            <div class="e-books">
                <div class="title">
                    <h1>世界の果てとそのまた世界の果て</h1>
                    <p>2007</p>
                    <div class="text">
                        <p>シーンと一緒だった頃、僕は夜の街で踊っていた。トルーマン・カポーティの代表作『ティファニーで朝食を』から閃きを受けた著者の初期作品。暗い夜の世界で生きる若者たちに捧げるストーリー。</p>
                    </div>
                    <div class="btn">
                        <a href="https://end2endworld.com/?page_id=29">Read</a>
                    </div>
                </div>
            </div>
            <div class="e-books">
                <div class="title">
                    <h1>DRIVING</h1>
                    <p>2013</p>
                    <div class="text">
                        <p>車を運転する。どこかへ走り出す。でもそれは誰もが手にしているものではない。失業中の僕を誘い出した後輩の女の子の言葉に、人生の奥深さを感じさせられる男のショート・ストーリー。</p>
                    </div>
                    <div class="btn">
                        <a href="https://end2endworld.com/?page_id=33">Read</a>
                    </div>
                </div>
            </div>
        </div>
    </section> -->

    <!-- <section class="block-essay">
        <div class="block-header">
            <h1>エッセイ</h1>
        </div>
        <div class="block-essay">
            <?php
            $args = array(
                'category_name' => 'essay',
                'posts_per_page' => 10
            );
            $the_query = new WP_Query($args);
            if ($the_query->have_posts()) {
                while ($the_query->have_posts()) {
                    $the_query->the_post();
                    echo '<div class="e-books">';
                    echo '<h2><a href="' . esc_url(get_permalink()) . '">' . get_the_title() . '</a></h2>';
                    echo '</div>';
                }
                wp_reset_postdata();
            }
            ?>
        </div>
    </section>-->
	<!-- エッセイ セクション -->
  <section class="block-essay" id="essays">
    <div class="block-header">
      <h1>エッセイ</h1>
    </div>
    <div class="block-essay">
      <?php
      $args = array(
        'category_name'  => 'essay',
        'posts_per_page' => 10
      );
      $the_query = new WP_Query($args);
      if ($the_query->have_posts()) {
        while ($the_query->have_posts()) {
          $the_query->the_post();
          echo '<div class="essay-item">';
          echo '<h2><a href="' . esc_url(get_permalink()) . '">' . get_the_title() . '</a></h2>';
          echo '</div>';
        }
        wp_reset_postdata();
      }
      ?>
    </div>
  </section>

  <section class="block4 clearfix" id="short-stories">
        <div class="block-header">
            <h1>短編音楽小説</h1>
            <p>音楽を閃きにしたショートストーリー集</p>
        </div>
        <div class="smn-wrapper">
            <?php
            $args = array(
                'category_name' => 'smn',
                'posts_per_page' => -1 // すべての投稿を取得
            );
            $the_query = new WP_Query($args);
            if ($the_query->have_posts()) {
                while ($the_query->have_posts()) {
                    $the_query->the_post();
                    echo '<div class="e-books">';
                    echo '<div class="yt">';
                    
                    $post_id = get_the_ID();
                    // 投稿IDが1140の場合は指定のサムネイルを表示
                    if ($post_id == 1140) {
                        $thumbnailUrl = "http://end2endworld.org/wp-content/uploads/2025/03/u.png"; // 仮の指定サムネイルURL
                        echo "<a href='" . esc_url(get_permalink()) . "' class='thumbnail230'>";
                        echo "<img class='y_thumbnail' src='" . esc_url($thumbnailUrl) . "' alt=''></a>";
                    } else {
                        $youtubePost = get_the_content();
                        if (preg_match('/youtube\.com\/watch\?v=([-\w]+)/', $youtubePost, $matches)) {
                            $youtubeId = $matches[1];
                            $thumbnailUrl = "https://img.youtube.com/vi/{$youtubeId}/0.jpg";
                            echo "<a href='" . esc_url(get_permalink()) . "' class='thumbnail230'>";
                            echo "<img class='y_thumbnail' src='" . esc_url($thumbnailUrl) . "' alt=''></a>";
                        }
                    }
                    echo '</div><div class="tx"><p>';
                    the_title();
                    echo '</p></div></div>';
                }
                wp_reset_postdata();
            }
            ?>
        </div>
    </section>

    <div class="footer">
        <div class="footer-text">
<p>
    このウェブサイトのコンテンツ（特記のない限り）は、
    <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">
      Creative Commons Attribution 4.0 International License（CC BY 4.0）
    </a>
    に基づいて提供されています。 © abejunichi
  </p>
  <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">
    <img src="https://i.creativecommons.org/l/by/4.0/88x31.png" alt="CC BY 4.0">
  </a>

© abejunichi</p>
			<a href="https://end2endworld.org/archives/980">プライバシーポリシー</a>
        </div>
    </div>
    <script>
        // モバイルメニューのトグル
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            const nav = document.getElementById('main-nav');
            nav.classList.toggle('active');
        });

        // スムーススクロール
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // モバイルメニューを閉じる
                    document.getElementById('main-nav').classList.remove('active');
                }
            });
        });

        // メニュー以外をクリックしたときにメニューを閉じる
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('main-nav');
            const toggle = document.getElementById('mobile-menu-toggle');
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('active');
            }
        });
    </script>
</body>

</html>