// This file is part of Moodle - http://moodle.org/ //
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mathjax JS Loader.
 *
 * @module filter_mathjaxloader/loader
 * @copyright 2014 Damyon Wiese  <damyon@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
    eventTypes,
    notifyFilterContentRenderingComplete,
} from 'core_filters/events';
import * as Scoped from 'filter_mathjaxloader/scoped';

// Keep the administrator configuration separate from MathJax runtime defaults.
let adminTexConfig = {};
let configured = false;

/**
 * URL to MathJax.
 * @type {string|null}
 */
let mathJaxUrl = null;

/**
 * Promise that is resolved when MathJax was loaded.
 * @type {Promise|null}
 */
let mathJaxLoaded = null;

/**
 * Called by the filter when it is active on any page.
 * This does not load MathJAX yet - it adds the configuration in case it gets loaded later.
 * It also subscribes to the filter-content-updated event so MathJax can respond to content loaded by Ajax.
 *
 * @param {Object} params List of configuration params containing mathjaxurl, mathjaxconfig (text) and lang
 */
export const configure = (params) => {
    let config = {};
    try {
        if (params.mathjaxconfig !== '') {
            config = JSON.parse(params.mathjaxconfig);
        }
    }
    catch (e) {
        window.console.error('Invalid JSON in mathjaxconfig.', e);
    }
    if (typeof config != 'object') {
        config = {};
    }
    if (typeof config.loader !== 'object') {
        config.loader = {};
    }
    if (!Array.isArray(config.loader.load)) {
        config.loader.load = [];
    }
    if (typeof config.startup !== 'object') {
        config.startup = {};
    }

    // Always ensure that ui/safe is in the list. Otherwise, there is a risk of XSS.
    // https://docs.mathjax.org/en/v3.2-latest/options/safe.html.
    if (!config.loader.load.includes('ui/safe')) {
        config.loader.load.push('ui/safe');
    }

    // This filter controls what elements to typeset.
    config.startup.typeset = false;

    // Let's still set the locale even if the localization is not yet ported to version 3.2.2
    // https://docs.mathjax.org/en/v3.2-latest/upgrading/v2.html#not-yet-ported-to-version-3.
    config.locale = params.lang;

    mathJaxUrl = params.mathjaxurl;

    // Only set window.MathJax if MathJax has not started loading yet.
    // configure() may be called more than once when AJAX fragments are loaded (e.g., block edit forms).
    if (!mathJaxLoaded) {
        window.MathJax = config;
        adminTexConfig = JSON.parse(JSON.stringify(config.tex || {}));
    }

    // Listen for events triggered when new text is added to a page that needs
    // processing by a filter.
    document.addEventListener(eventTypes.filterContentUpdated, contentUpdated);
    configured = true;
};

/**
 * Add the node to the typeset queue.
 *
 * @param {HTMLElement} node The Node to be processed by MathJax
 * @private
 */
const typesetNode = (node) => {
    if (!(node instanceof HTMLElement)) {
        // We may have been passed a #text node.
        // These cannot be formatted.
        return;
    }

    return queueTypeset(node).catch(e => window.console.log(e));
};

/**
 * Serialize both ordinary and scoped rendering with the shared MathJax queue.
 *
 * @param {HTMLElement} node Container to render
 * @returns {Promise} Rendering completion
 */
const queueTypeset = node => loadMathJax().then(() => {
    const result = window.MathJax.startup.promise.then(async() => {
        const scope = Scoped.find(node);
        if (scope) {
            await Scoped.render(scope);
        } else {
            await Scoped.renderOrdinary(node);
        }
        notifyFilterContentRenderingComplete([node]);
    });
    // A rejected contribution must not disable unrelated mathematics on the page.
    window.MathJax.startup.promise = result.catch(() => {});
    return result;
});

/**
 * Typeset a trusted plugin-owned container with isolated TeX configuration.
 *
 * Call once, after configure() and before the container's first filter update.
 * AJAX updates within the registered element reuse its configuration. A replacement
 * element must be registered explicitly. Never select the element or configuration
 * from user-authored markup, and never include untrusted mathematics in the scope.
 * Requires MathJax 4. Explicit administrator options cannot be overridden.
 *
 * @param {HTMLElement} node Connected, not-yet-typeset container owned by the calling plugin
 * @param {Object} config Built-in package names in packages and additional options in tex
 * @returns {Promise} Initial rendering completion; rejects invalid or conflicting contributions
 */
export const typesetWithConfig = async(node, config) => {
    if (!configured) {
        throw new Error('Configure the MathJax filter before registering a scope.');
    }
    Scoped.register(node, config, adminTexConfig);
    try {
        await queueTypeset(node);
    } catch (error) {
        Scoped.unregister(node);
        throw error;
    }
};

/**
 * Called by the filter when an equation is found while rendering the page.
 */
export const typeset = () => {
    const elements = document.getElementsByClassName('filter_mathjaxloader_equation');
    for (const element of elements) {
        typesetNode(element);
    }
};

/**
 * Handle content updated events - typeset the new content.
 *
 * @param {CustomEvent} event - Custom event with "nodes" indicating the root of the updated nodes.
 */
export const contentUpdated = (event) => {
    const nodes = new Set();
    event.detail.nodes.forEach(node => {
        if (!(node instanceof HTMLElement)) {
            return;
        }
        const scope = Scoped.find(node);
        if (scope) {
            nodes.add(scope.node);
        } else {
            if (node.matches('.filter_mathjaxloader_equation')) {
                nodes.add(node);
            }
            node.querySelectorAll('.filter_mathjaxloader_equation').forEach(element => nodes.add(element));
            Scoped.within(node).forEach(element => nodes.add(element));
        }
    });
    nodes.forEach(node => typesetNode(node));
};

/**
 * Load the MathJax script.
 *
 * @return Promise that is resolved when MathJax was loaded.
 */
export const loadMathJax = () => {
    if (!mathJaxLoaded) {
        if (!mathJaxUrl) {
            return Promise.reject(new Error('URL to MathJax not set.'));
        }

        mathJaxLoaded = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.onload = resolve;
            script.onerror = reject;
            script.src = mathJaxUrl;
            document.getElementsByTagName('head')[0].appendChild(script);
        });
    }
    return mathJaxLoaded;
};
