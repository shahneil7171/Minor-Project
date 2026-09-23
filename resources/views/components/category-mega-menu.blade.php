{{--
    KDP MART — Categories mega menu (single implementation, used by every header).

    Data driven: receives the same active top-level categories the page already
    loaded (with their active subcategories eager-loaded), so rendering the menu
    performs no additional queries and cannot drift from the database.

      • resources/views/home.blade.php      -> $homeCategories / $categoryCounts
      • resources/views/layouts/app.blade.php -> $navCategories (view composer)

    Layout: two columns (main categories | subcategories of the highlighted one)
    on desktop, and a drill-down panel (categories -> subcategories + Back) on
    mobile/tablet. Panels are pre-rendered and swapped client-side, so hovering
    never triggers a request.

    Product counts are optional and only shown when the caller already has them
    ($categoryCounts on the home page is computed from the in-memory catalog) —
    never by adding a per-category query here.
--}}
@props([
    'categories' => null,
    'counts' => [],
    'label' => 'Categories',
])

@php
    $categories = $categories instanceof \Illuminate\Support\Collection
        ? $categories
        : collect($categories);

    $counts = $counts instanceof \Illuminate\Support\Collection ? $counts->all() : (array) $counts;

    // Highlight the category currently being browsed (/categories/{slug}), falling
    // back to the first one. Pure in-memory lookup — no database access.
    $currentSlug = request()->routeIs('categories.show') ? (string) request()->route('slug') : '';
    $activeId = null;

    foreach ($categories as $category) {
        if ($currentSlug !== '' && $category->slug === $currentSlug) {
            $activeId = $category->id;
            break;
        }

        foreach ($category->children as $child) {
            if ($currentSlug !== '' && $child->slug === $currentSlug) {
                $activeId = $category->id;
                break 2;
            }
        }
    }

    if ($activeId === null && $categories->isNotEmpty()) {
        $activeId = $categories->first()->id;
    }

    $uid = 'kdpMega';
@endphp

<nav class="kdp-mega" id="{{ $uid }}" aria-label="{{ $label }}" data-kdp-mega>
    <button
        type="button"
        class="kdp-mega__toggle"
        id="{{ $uid }}-toggle"
        aria-expanded="false"
        aria-controls="{{ $uid }}-panel"
        aria-haspopup="true"
    >
        <span>{{ $label }}</span>
        <span class="kdp-mega__caret" aria-hidden="true"></span>
    </button>

    {{-- Hover tolerance: invisible strip bridging the gap between the toggle and
         the panel so the pointer can travel into either column. --}}
    <span class="kdp-mega__bridge" aria-hidden="true"></span>

    <div class="kdp-mega__panel" id="{{ $uid }}-panel" role="region" aria-label="{{ $label }} menu">
        <div class="kdp-mega__head">
            <span class="kdp-mega__eyebrow">{{ $label }}</span>
            <a class="kdp-mega__browse" href="{{ route('products') }}">
                Browse all products <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        @if ($categories->isEmpty())
            <p class="kdp-mega__empty">
                No categories available yet.
                <a href="{{ route('products') }}">View all products <span aria-hidden="true">&rarr;</span></a>
            </p>
        @else
            <div class="kdp-mega__grid">
                {{-- ===== Column 1: main categories ===== --}}
                <div class="kdp-mega__col-main">
                    <a class="kdp-mega__all" href="{{ route('products') }}">
                        <span class="kdp-mega__name">All Categories</span>
                    </a>

                    <ul class="kdp-mega__cats" role="list">
                        @foreach ($categories as $category)
                            <li>
                                <a
                                    class="kdp-mega__cat {{ (int) $category->id === (int) $activeId ? 'is-active' : '' }}"
                                    href="{{ route('categories.show', $category->slug) }}"
                                    data-kdp-cat="{{ $category->id }}"
                                    data-kdp-has-children="{{ $category->children->isNotEmpty() ? '1' : '0' }}"
                                    aria-controls="{{ $uid }}-sub-{{ $category->id }}"
                                >
                                    <span class="kdp-mega__name">{{ $category->name }}</span>
                                    @if (array_key_exists($category->id, $counts))
                                        <span class="kdp-mega__count">{{ (int) $counts[$category->id] }}</span>
                                    @endif
                                    <span class="kdp-mega__chev" aria-hidden="true"></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- ===== Column 2: subcategories of the highlighted category ===== --}}
                <div class="kdp-mega__col-subs">
                    @foreach ($categories as $category)
                        <section
                            class="kdp-mega__sub {{ (int) $category->id === (int) $activeId ? 'is-active' : '' }}"
                            id="{{ $uid }}-sub-{{ $category->id }}"
                            data-kdp-sub="{{ $category->id }}"
                            aria-label="{{ $category->name }} subcategories"
                        >
                            <button type="button" class="kdp-mega__back" data-kdp-back>
                                <span aria-hidden="true">&lsaquo;</span> Back to categories
                            </button>

                            <p class="kdp-mega__eyebrow">{{ $category->name }}</p>

                            <div class="kdp-mega__subhead">
                                <h3 class="kdp-mega__title">{{ $category->name }}</h3>
                                <a class="kdp-mega__viewall" href="{{ route('categories.show', $category->slug) }}">
                                    View All {{ $category->name }} <span aria-hidden="true">&rarr;</span>
                                </a>
                            </div>

                            <p class="kdp-mega__helper">Browse all {{ $category->name }} products</p>

                            @if ($category->children->isNotEmpty())
                                <p class="kdp-mega__eyebrow kdp-mega__eyebrow--subs">Subcategories</p>

                                <ul class="kdp-mega__subs" role="list">
                                    @foreach ($category->children as $child)
                                        <li>
                                            <a href="{{ route('categories.show', $child->slug) }}">
                                                <span class="kdp-mega__subname">{{ $child->name }}</span>
                                                @if (array_key_exists($child->id, $counts))
                                                    <span class="kdp-mega__count">{{ (int) $counts[$child->id] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                {{-- Never render a blank panel for a category without children. --}}
                                <p class="kdp-mega__empty">
                                    No subcategories available.
                                    <a href="{{ route('products') }}">View all products <span aria-hidden="true">&rarr;</span></a>
                                </p>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</nav>


@once
<style>
    /* ==========================================================================
       KDP MART — Categories mega menu
       Dark surface + subtle border + existing accent (#2563eb / #3b82f6) and the
       same rounded corners / shadow used by the rest of the storefront.
       Mobile-first: an inline drill-down panel by default, a two-column mega
       menu from 992px up. No rule may introduce a horizontal scrollbar or clip a
       category name (no overflow-x:auto, no text-overflow on names).
       ========================================================================== */
    .kdp-mega {
        --kdp-mega-surface: #0b1220;
        --kdp-mega-surface-2: #111827;
        --kdp-mega-border: rgba(255, 255, 255, 0.14);
        --kdp-mega-text: #e5e7eb;
        --kdp-mega-muted: #94a3b8;
        --kdp-mega-accent: #2563eb;
        --kdp-mega-accent-soft: #93c5fd;
        --kdp-mega-z: 70;
        position: relative;
        display: inline-flex;
        text-align: left;
    }

    /* ---------- Toggle ---------- */
    .kdp-mega__toggle {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: rgba(255, 255, 255, 0.9);
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 600;
        line-height: 1.45;
        cursor: pointer;
        transition: background .2s ease, color .2s ease, border-color .2s ease;
    }
    .kdp-mega__toggle:hover,
    .kdp-mega.is-open > .kdp-mega__toggle {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
    .kdp-mega__toggle:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }
    .kdp-mega__caret {
        flex: 0 0 auto;
        width: 7px;
        height: 7px;
        margin-top: -3px;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(45deg);
        transition: transform .2s ease, margin .2s ease;
    }
    .kdp-mega.is-open .kdp-mega__caret {
        margin-top: 3px;
        transform: rotate(225deg);
    }

    /* ---------- Panel (shared) ---------- */
    .kdp-mega__panel {
        display: none;
        width: 100%;
        max-width: 100%;
        margin-top: 8px;
        background: var(--kdp-mega-surface);
        border: 1px solid var(--kdp-mega-border);
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.45);
        color: var(--kdp-mega-text);
        font-size: 0.9rem;
        /* Clips the rounded corners over the columns' own scrollbars only. */
        overflow: hidden;
    }
    .kdp-mega.is-open .kdp-mega__panel {
        display: block;
    }
    .kdp-mega__bridge {
        display: none;
    }

    .kdp-mega__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 10px 14px;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .kdp-mega__eyebrow {
        margin: 0;
        color: var(--kdp-mega-accent-soft);
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }
    .kdp-mega__eyebrow--subs {
        margin: 4px 0 6px;
        color: var(--kdp-mega-muted);
        letter-spacing: 0.08em;
    }
    .kdp-mega__browse {
        color: #bfdbfe;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }
    .kdp-mega__browse:hover,
    .kdp-mega__browse:focus-visible {
        color: #fff;
        text-decoration: underline;
    }

    /* ---------- Columns: mobile = single inline column with drill-down ---------- */
    .kdp-mega__grid {
        display: block;
    }
    .kdp-mega__col-main {
        padding: 10px;
        max-height: 46vh;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .kdp-mega__col-subs {
        display: none;
        padding: 12px;
        overflow-x: hidden;
    }
    .kdp-mega.is-drilled .kdp-mega__col-main {
        display: none;
    }
    .kdp-mega.is-drilled .kdp-mega__col-subs {
        display: block;
        max-height: 62vh;
        overflow-y: auto;
    }

    /* One subcategory panel at a time — all of them are pre-rendered. */
    .kdp-mega__sub {
        display: none;
    }
    .kdp-mega.is-drilled .kdp-mega__sub.is-active {
        display: block;
    }

    .kdp-mega,
    .kdp-mega * {
        box-sizing: border-box;
    }

    /* ---------- Rows: categories + subcategories ---------- */
    /* Long names always wrap — never clipped, never ellipsised, never
       horizontally scrollable. */
    .kdp-mega__all {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        padding: 9px 12px;
        border-radius: 10px;
        background: rgba(37, 99, 235, 0.16);
        border: 1px solid rgba(59, 130, 246, 0.32);
        color: #dbeafe;
        font-size: 0.88rem;
        font-weight: 700;
        text-decoration: none;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .kdp-mega__all:hover,
    .kdp-mega__all:focus-visible {
        background: rgba(37, 99, 235, 0.3);
        color: #fff;
    }

    .kdp-mega__cats,
    .kdp-mega__subs {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .kdp-mega__cat {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border-radius: 10px;
        color: #cbd5e1;
        font-size: 0.92rem;
        font-weight: 600;
        line-height: 1.4;
        text-decoration: none;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
    }
    .kdp-mega__cat:hover,
    .kdp-mega__cat:focus-visible {
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
    }
    /* Highlighted category: accent tint + accent rail, no heavy borders. */
    .kdp-mega__cat.is-active {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.3), rgba(29, 78, 216, 0.14));
        color: #fff;
        font-weight: 800;
        box-shadow: inset 3px 0 0 #3b82f6;
    }
    .kdp-mega__name,
    .kdp-mega__subname {
        flex: 1 1 auto;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .kdp-mega__chev {
        flex: 0 0 auto;
        width: 7px;
        height: 7px;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(45deg);
        opacity: 0.7;
    }
    .kdp-mega__cat.is-active .kdp-mega__chev {
        opacity: 1;
    }
    .kdp-mega__count {
        flex: 0 0 auto;
        padding: 1px 8px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.18);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: var(--kdp-mega-accent-soft);
        font-size: 0.7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    /* ---------- Subcategory column content ---------- */
    .kdp-mega__back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 10px;
        padding: 7px 12px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: rgba(255, 255, 255, 0.05);
        color: var(--kdp-mega-text);
        font-family: inherit;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
    }
    .kdp-mega__back:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
    }
    .kdp-mega__subhead {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin: 4px 0 2px;
    }
    .kdp-mega__title {
        margin: 0;
        color: #fff;
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .kdp-mega__viewall {
        color: var(--kdp-mega-accent-soft);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }
    .kdp-mega__viewall:hover,
    .kdp-mega__viewall:focus-visible {
        color: #dbeafe;
        text-decoration: underline;
    }
    .kdp-mega__helper {
        margin: 0 0 8px;
        color: var(--kdp-mega-muted);
        font-size: 0.78rem;
    }
    .kdp-mega__subs {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 2px;
    }
    .kdp-mega__subs a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 8px;
        color: #cbd5e1;
        font-size: 0.88rem;
        font-weight: 500;
        line-height: 1.45;
        text-decoration: none;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
    }
    .kdp-mega__subs a:hover,
    .kdp-mega__subs a:focus-visible {
        background: rgba(255, 255, 255, 0.07);
        color: #fff;
    }
    .kdp-mega__empty {
        margin: 0;
        padding: 14px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px dashed rgba(255, 255, 255, 0.16);
        color: var(--kdp-mega-muted);
        font-size: 0.85rem;
    }
    .kdp-mega__empty a {
        color: var(--kdp-mega-accent-soft);
        font-weight: 700;
        text-decoration: none;
    }

    /* ---------- Dark scrollbars (vertical only) ---------- */
    .kdp-mega__col-main,
    .kdp-mega__col-subs {
        scrollbar-width: thin;
        scrollbar-color: #3b82f6 rgba(255, 255, 255, 0.06);
    }
    .kdp-mega__col-main::-webkit-scrollbar,
    .kdp-mega__col-subs::-webkit-scrollbar {
        width: 10px;
        height: 0;
    }
    .kdp-mega__col-main::-webkit-scrollbar-thumb,
    .kdp-mega__col-subs::-webkit-scrollbar-thumb {
        background: #3b82f6;
        border: 2px solid var(--kdp-mega-surface);
        border-radius: 999px;
    }
    .kdp-mega__col-main::-webkit-scrollbar-track,
    .kdp-mega__col-subs::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    /* ==========================================================================
       Desktop / large tablet (>= 992px): the two-column mega menu.
       Width is viewport-relative (min(90vw, 950px)) and capped by
       calc(100vw - 30px), so it can never overflow horizontally; the height is
       capped via --kdp-mega-max-h (set by the script) and each column scrolls
       vertically on its own.
       ========================================================================== */
    @media (min-width: 992px) {
        .kdp-mega__panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: min(90vw, 950px);
            max-width: calc(100vw - 30px);
            max-height: var(--kdp-mega-max-h, 560px);
            margin-top: 0;
            z-index: var(--kdp-mega-z);
        }
        .kdp-mega.is-open .kdp-mega__bridge {
            display: block;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            height: 12px;
            z-index: calc(var(--kdp-mega-z) - 1);
        }
        .kdp-mega__grid {
            display: grid;
            grid-template-columns: minmax(220px, 30%) minmax(0, 1fr);
        }
        .kdp-mega__col-main {
            max-height: 500px;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }
        .kdp-mega__col-subs {
            display: block;
            max-height: 500px;
            overflow-y: auto;
        }
        /* The columns are always side by side on desktop, even if the drilled
           state was left over from a smaller viewport. */
        .kdp-mega.is-drilled .kdp-mega__col-main {
            display: block;
        }
        .kdp-mega.is-drilled .kdp-mega__col-subs {
            display: block;
            max-height: 500px;
        }
        .kdp-mega__back {
            display: none;
        }
        .kdp-mega__sub {
            display: none;
        }
        .kdp-mega__sub.is-active {
            display: block;
        }
        .kdp-mega__subs {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 2px 14px;
        }
        .kdp-mega__chev {
            transform: rotate(-45deg);
        }
    }

    /* ==========================================================================
       Header integration (no layout duplication):
       • the dark storefront header (.nav) keeps the toggle looking exactly like
         its sibling nav links,
       • the shared layout's gradient navbar makes it match .kdp-nav .nav-link,
       • any other header gets the neutral base styling above.
       ========================================================================== */
    .nav .kdp-mega__toggle {
        color: #cbd5e1;
    }
    .nav .kdp-mega__toggle:hover,
    .nav .kdp-mega.is-open > .kdp-mega__toggle {
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
    }
    .kdp-navbar .kdp-mega__toggle {
        padding: 12px 14px;
        border-radius: 0;
        border-bottom: 3px solid transparent;
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.92rem;
    }
    .kdp-navbar .kdp-mega__toggle:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
    }
    .kdp-navbar .kdp-mega.is-open > .kdp-mega__toggle {
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
        border-bottom-color: #ffd54f;
    }
    .kdp-navbar .kdp-mega__toggle:focus-visible {
        outline-offset: -2px;
    }

    /* ==========================================================================
       Mobile / tablet (< 992px): a single full-width drill-down panel that
       always fits the screen — no horizontal scrolling, no clipped names.
       ========================================================================== */
    @media (max-width: 991.98px) {
        .kdp-mega {
            display: block;
            width: 100%;
        }
        .kdp-mega__toggle {
            width: 100%;
            justify-content: flex-start;
        }
        .kdp-navbar .kdp-mega__toggle {
            padding: 12px 14px;
            border-bottom: none;
            border-left: 3px solid transparent;
            border-radius: 0;
        }
        .kdp-navbar .kdp-mega.is-open > .kdp-mega__toggle {
            border-left-color: #ffd54f;
        }
    }
</style>
@endonce


@once
<script>
    // KDP MART — Categories mega menu behaviour.
    //  • Click "Categories" toggles the panel (aria-expanded stays in sync).
    //  • Hovering / tabbing a main category swaps the subcategory column.
    //  • Mobile: tapping a category with children drills in (Back returns).
    //  • Closes on: toggle click, outside click, Escape, or any navigation.
    //  • Every panel is pre-rendered from the database, so hovering never
    //    triggers a request - no per-hover queries.
    (function () {
        'use strict';

        var DESKTOP_QUERY = '(min-width: 992px)';
        var VIEWPORT_GAP = 14;

        function isDesktop() {
            return window.matchMedia
                ? window.matchMedia(DESKTOP_QUERY).matches
                : window.innerWidth >= 992;
        }

        function closest(element, selector) {
            var node = element;
            while (node && node.nodeType === 1) {
                if (node.matches && node.matches(selector)) { return node; }
                node = node.parentNode;
            }
            return null;
        }

        function initMega(nav) {
            var toggle = nav.querySelector('.kdp-mega__toggle');
            var panel = nav.querySelector('.kdp-mega__panel');
            if (!toggle || !panel) { return; }

            var catLinks = Array.prototype.slice.call(nav.querySelectorAll('.kdp-mega__cat'));
            var subPanels = Array.prototype.slice.call(nav.querySelectorAll('.kdp-mega__sub'));

            function isOpen() {
                return nav.classList.contains('is-open');
            }

            function activate(id) {
                if (id === null || typeof id === 'undefined') { return; }

                catLinks.forEach(function (link) {
                    link.classList.toggle('is-active', link.getAttribute('data-kdp-cat') === String(id));
                });
                subPanels.forEach(function (section) {
                    section.classList.toggle('is-active', section.getAttribute('data-kdp-sub') === String(id));
                });
            }

            // Keeps the absolutely positioned panel inside the viewport on both
            // axes: clamps the horizontal offset and caps the height so the
            // columns scroll internally instead of overflowing the page.
            function fit() {
                panel.style.removeProperty('--kdp-mega-max-h');

                if (!isDesktop()) {
                    panel.style.left = '';
                    panel.style.right = '';
                    return;
                }

                panel.style.left = '0';
                panel.style.right = 'auto';

                var navRect = nav.getBoundingClientRect();
                var panelRect = panel.getBoundingClientRect();
                var minLeft = VIEWPORT_GAP - navRect.left;
                var maxLeft = (window.innerWidth - VIEWPORT_GAP - navRect.left) - panelRect.width;
                panel.style.left = Math.round(Math.min(0, Math.max(minLeft, maxLeft))) + 'px';

                var top = panel.getBoundingClientRect().top;
                var maxHeight = Math.max(260, window.innerHeight - top - VIEWPORT_GAP);
                panel.style.setProperty('--kdp-mega-max-h', Math.round(maxHeight) + 'px');
            }

            function onKeydown(event) {
                if (event.key === 'Escape' || event.key === 'Esc') {
                    event.preventDefault();
                    close(true);
                }
            }

            function open() {
                nav.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
                document.addEventListener('keydown', onKeydown);
                fit();
            }

            function close(restoreFocus) {
                if (!isOpen()) { return; }
                nav.classList.remove('is-open');
                nav.classList.remove('is-drilled');
                toggle.setAttribute('aria-expanded', 'false');
                document.removeEventListener('keydown', onKeydown);
                if (restoreFocus) { toggle.focus(); }
            }

            function visibleFocusable() {
                return Array.prototype.filter.call(
                    panel.querySelectorAll('a[href], button:not([disabled])'),
                    function (element) { return element.offsetParent !== null; }
                );
            }


            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                if (isOpen()) { close(false); } else { open(); }
            });

            // Hover (desktop) and keyboard focus both keep the right column in
            // sync with the highlighted category.
            nav.addEventListener('mouseover', function (event) {
                var link = closest(event.target, '.kdp-mega__cat');
                if (link && nav.contains(link)) { activate(link.getAttribute('data-kdp-cat')); }
            });

            nav.addEventListener('focusin', function (event) {
                var link = closest(event.target, '.kdp-mega__cat');
                if (link) { activate(link.getAttribute('data-kdp-cat')); }
            });

            nav.addEventListener('click', function (event) {
                var back = closest(event.target, '[data-kdp-back]');
                if (back) {
                    event.preventDefault();
                    nav.classList.remove('is-drilled');
                    var active = nav.querySelector('.kdp-mega__cat.is-active');
                    if (active) { active.focus(); }
                    return;
                }

                var link = closest(event.target, '.kdp-mega__cat');
                if (!link) { return; }

                // Mobile/tablet: tapping a category that has subcategories opens
                // them instead of navigating; without JS the anchor navigates.
                if (!isDesktop() && link.getAttribute('data-kdp-has-children') === '1') {
                    event.preventDefault();
                    activate(link.getAttribute('data-kdp-cat'));
                    nav.classList.add('is-drilled');
                    var backButton = panel.querySelector('.kdp-mega__sub.is-active .kdp-mega__back');
                    if (backButton) { backButton.focus(); }
                    return;
                }

                close(false); // navigating to the category page
            });

            // Subcategory / All Categories / View All links navigate, so close.
            panel.addEventListener('click', function (event) {
                if (closest(event.target, 'a[href]')) { close(false); }
            });

            // Arrow keys move through the menu's links and buttons.
            nav.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') { return; }
                var items = visibleFocusable();
                if (!items.length) { return; }
                var index = items.indexOf(document.activeElement);
                event.preventDefault();
                var next = event.key === 'ArrowDown'
                    ? (index < 0 ? 0 : Math.min(items.length - 1, index + 1))
                    : (index <= 0 ? 0 : index - 1);
                items[next].focus();
            });

            // Predictable closing: anywhere outside the menu.
            document.addEventListener('click', function (event) {
                if (isOpen() && !nav.contains(event.target)) { close(false); }
            });

            window.addEventListener('resize', function () {
                if (!isOpen()) { return; }
                nav.classList.remove('is-drilled');
                fit();
            });
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-kdp-mega]'), initMega);
    })();
</script>
@endonce

