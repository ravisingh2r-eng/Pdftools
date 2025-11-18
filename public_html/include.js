/**
 * Include.js - Dynamic HTML Include System
 *
 * Finds all elements with data-include attribute and fetches
 * the corresponding HTML file, injecting it into the element.
 */

(function() {
    'use strict';

    /**
     * Load all includes on the page
     */
    function loadIncludes() {
        const includes = document.querySelectorAll('[data-include]');

        if (includes.length === 0) {
            return;
        }

        const promises = Array.from(includes).map(element => {
            const file = element.getAttribute('data-include');

            if (!file) {
                console.warn('Empty data-include attribute found');
                return Promise.resolve();
            }

            return fetchAndInclude(element, file);
        });

        // Wait for all includes to load, then dispatch event
        Promise.all(promises).then(() => {
            document.dispatchEvent(new CustomEvent('includesLoaded'));
        });
    }

    /**
     * Fetch HTML file and inject into element
     *
     * @param {HTMLElement} element - Target element
     * @param {string} file - Path to HTML file
     * @returns {Promise}
     */
    function fetchAndInclude(element, file) {
        return fetch(file)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to load ${file}: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                element.innerHTML = html;

                // Execute any scripts in the included HTML
                executeScripts(element);

                // Dispatch event for this specific include
                element.dispatchEvent(new CustomEvent('includeLoaded', {
                    detail: { file: file }
                }));
            })
            .catch(error => {
                console.error(`Include error: ${error.message}`);
                element.innerHTML = `<!-- Failed to load: ${file} -->`;
            });
    }

    /**
     * Execute scripts found in dynamically loaded HTML
     *
     * @param {HTMLElement} container - Container with scripts
     */
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');

        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');

            // Copy attributes
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copy inline script content
            newScript.textContent = oldScript.textContent;

            // Replace old script with new one to execute it
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    // Load includes when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadIncludes);
    } else {
        loadIncludes();
    }

    // Expose function for manual reloading if needed
    window.reloadIncludes = loadIncludes;

})();
