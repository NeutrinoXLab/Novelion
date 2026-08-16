import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {

    const input = document.getElementById('search-input');
    const results = document.getElementById('search-results');

    if (!input || !results) {
        return;
    }

    let timeout = null;

    input.addEventListener('input', () => {

        clearTimeout(timeout);

        const query = input.value.trim();

        if (query.length < 2) {

            results.classList.add('hidden');
            results.innerHTML = '';

            return;
        }

        timeout = setTimeout(async () => {

            try {

                const response = await fetch('/search?q=' + encodeURIComponent(query));

                const products = await response.json();

                if (products.length === 0) {

                    results.innerHTML = `
                        <div class="p-4 text-center text-slate-500">
                            Nu s-au găsit produse.
                        </div>
                    `;

                    results.classList.remove('hidden');

                    return;
                }

                let html = '';

                products.forEach(product => {

                    const image = product.primary_image_path
                        ? '/storage/' + product.primary_image_path
                        : '';

                    const price = product.sale_price ?? product.selling_price;

                    html += `
                        <a href="/produs/${product.slug}"
                           class="flex items-center gap-4 p-4 hover:bg-slate-50 transition">

                            ${
                                image
                                ? `<img src="${image}" class="w-14 h-14 rounded-xl object-cover">`
                                : `<div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center">📦</div>`
                            }

                            <div class="flex-1">

                                <div class="font-semibold text-slate-800">
                                    ${product.name}
                                </div>

                                <div class="text-cyan-600 font-bold">
                                    ${price} Lei
                                </div>

                            </div>

                        </a>
                    `;

                });

                results.innerHTML = html;
                results.classList.remove('hidden');

            } catch (e) {

                console.error(e);

            }

        }, 300);

    });

    document.addEventListener('click', (e) => {

        if (!results.contains(e.target) && e.target !== input) {

            results.classList.add('hidden');

        }

    });

});