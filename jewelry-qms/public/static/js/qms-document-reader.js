(function () {
    'use strict';

    const searchInput = document.querySelector('[data-document-search]');
    const resultLabel = document.querySelector('[data-document-search-result]');
    const content = document.querySelector('.qms-document-reader__content');
    const sectionLinks = Array.from(document.querySelectorAll('[data-section-link]'));
    const relations = document.querySelector('#document-relations');

    function openRelationsFromHash() {
        if (relations && window.location.hash === '#document-relations') {
            relations.open = true;
        }
    }

    openRelationsFromHash();
    window.addEventListener('hashchange', openRelationsFromHash);

    function clearHighlights() {
        document.querySelectorAll('.qms-readable-markdown mark').forEach(function (mark) {
            mark.replaceWith(document.createTextNode(mark.textContent || ''));
        });
        document.querySelectorAll('.qms-readable-markdown').forEach(function (node) {
            node.normalize();
        });
    }

    function highlight(term) {
        clearHighlights();
        if (!term || !content) {
            if (resultLabel) resultLabel.textContent = '';
            return;
        }
        const normalized = term.toLocaleLowerCase();
        let matches = 0;
        const walker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.nodeValue || !node.nodeValue.toLocaleLowerCase().includes(normalized)) {
                    return NodeFilter.FILTER_REJECT;
                }
                const parent = node.parentElement;
                if (!parent || parent.closest('mark, script, style')) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            const text = node.nodeValue || '';
            const lower = text.toLocaleLowerCase();
            const fragment = document.createDocumentFragment();
            let cursor = 0;
            let index = lower.indexOf(normalized, cursor);
            while (index !== -1) {
                fragment.appendChild(document.createTextNode(text.slice(cursor, index)));
                const mark = document.createElement('mark');
                mark.textContent = text.slice(index, index + term.length);
                fragment.appendChild(mark);
                matches += 1;
                cursor = index + term.length;
                index = lower.indexOf(normalized, cursor);
            }
            fragment.appendChild(document.createTextNode(text.slice(cursor)));
            node.replaceWith(fragment);
        });
        if (resultLabel) resultLabel.textContent = matches ? matches + ' 处' : '未找到';
        const first = document.querySelector('.qms-readable-markdown mark');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (searchInput) {
        let timer = 0;
        searchInput.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                highlight(searchInput.value.trim());
            }, 180);
        });
    }

    if ('IntersectionObserver' in window && sectionLinks.length) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                sectionLinks.forEach(function (link) {
                    link.classList.toggle('is-active', link.dataset.sectionLink === entry.target.id);
                });
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        document.querySelectorAll('[data-document-section]').forEach(function (section) {
            observer.observe(section);
        });
    }
})();
