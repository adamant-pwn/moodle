// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal support for independently configured MathJax documents.
 *
 * @module filter_mathjaxloader/scoped
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const marker = 'filter_mathjaxloader_scope';
const scopes = new WeakMap();
const forbidden = ['__proto__', 'prototype', 'constructor'];

/**
 * Copy a JSON configuration, rejecting non-data values and prototype keys.
 *
 * @param {*} value Configuration value
 * @returns {*} Independent copy
 */
const copy = value => {
    if (value === null || ['string', 'boolean'].includes(typeof value) ||
            (typeof value === 'number' && Number.isFinite(value))) {
        return value;
    }
    if (Array.isArray(value)) {
        return value.map(copy);
    }
    if (value && Object.getPrototypeOf(value) === Object.prototype) {
        const result = {};
        for (const [key, item] of Object.entries(value)) {
            if (forbidden.includes(key)) {
                throw new TypeError('Invalid MathJax configuration key.');
            }
            result[key] = copy(item);
        }
        return result;
    }
    throw new TypeError('MathJax configuration must contain JSON data only.');
};

/**
 * Merge options without replacing an explicit administrator value.
 *
 * @param {Object} base Administrator options
 * @param {Object} extra Plugin options
 * @returns {Object} Merged options
 */
const merge = (base, extra) => {
    const result = copy(base);
    for (const [key, value] of Object.entries(extra)) {
        if (!Object.hasOwn(result, key)) {
            result[key] = copy(value);
        } else if (value && result[key] && !Array.isArray(value) && !Array.isArray(result[key]) &&
                typeof value === 'object' && typeof result[key] === 'object') {
            result[key] = merge(result[key], value);
        } else if (JSON.stringify(result[key]) !== JSON.stringify(value)) {
            throw new Error(`Conflicting MathJax option: ${key}`);
        }
    }
    return result;
};

/**
 * Find the registered scope containing a node. Markup alone cannot register a scope.
 *
 * @param {HTMLElement} node Node to inspect
 * @returns {Object|null} Scope
 */
export const find = node => {
    for (let parent = node; parent; parent = parent.parentElement) {
        if (scopes.has(parent)) {
            return scopes.get(parent);
        }
    }
    return null;
};

/**
 * Find registered scopes within a container, including the container itself.
 *
 * @param {HTMLElement} node Container
 * @returns {HTMLElement[]} Scope roots
 */
export const within = node => [node, ...node.querySelectorAll(`.${marker}`)].filter(element => scopes.has(element));

/**
 * Register a scope synchronously, before ordinary typesetting can start.
 *
 * @param {HTMLElement} node Trusted container
 * @param {Object} config Plugin configuration
 * @param {Object} admin Administrator TeX configuration
 * @returns {Object} Scope
 */
export const register = (node, config, admin) => {
    if (!(node instanceof HTMLElement) || node.ownerDocument !== document || !node.isConnected) {
        throw new TypeError('A connected HTML element in the current document is required.');
    }
    if (find(node) || within(node).length) {
        throw new Error('MathJax scopes must not overlap or be registered twice.');
    }
    if (node.querySelector('mjx-container')) {
        throw new Error('Register the MathJax scope before its content is typeset.');
    }
    const contribution = copy(config);
    if (!contribution || Array.isArray(contribution) || typeof contribution !== 'object' ||
            Object.keys(contribution).some(key => !['packages', 'tex'].includes(key))) {
        throw new TypeError('Only packages and tex may be contributed.');
    }
    const packages = contribution.packages ?? [];
    const tex = contribution.tex ?? {};
    if (!Array.isArray(packages) || packages.some(name => typeof name !== 'string' || !/^[a-z][a-z0-9-]*$/.test(name))) {
        throw new TypeError('Packages must be built-in MathJax TeX package names.');
    }
    if (!tex || typeof tex !== 'object' || Array.isArray(tex) || Object.hasOwn(tex, 'packages')) {
        throw new TypeError('TeX options must be an object; use packages to add packages.');
    }
    if (packages.some(name => admin.packages?.['[-]']?.includes(name))) {
        throw new Error('A contributed MathJax package was disabled by the administrator.');
    }
    const scope = {node, packages: [...new Set(packages)].sort(), tex: merge(admin, tex), document: null};
    scopes.set(node, scope);
    node.classList.add(marker);
    return scope;
};

/**
 * Render a scope using its own input jax, output jax and MathDocument.
 *
 * Callers must serialize this with ordinary MathJax typesetting.
 *
 * @param {Object} scope Registered scope
 * @returns {Promise} Rendering completion
 */
export const render = async scope => {
    if (!scope.node.isConnected) {
        return;
    }
    const mathjax = window.MathJax;
    if (!scope.document) {
        if (!mathjax.version?.startsWith('4.')) {
            throw new Error('Scoped MathJax configuration requires MathJax 4.');
        }
        await mathjax.loader.load(...scope.packages.map(name => `[tex]/${name}`));
        const startup = mathjax.startup;
        const tex = startup.input.find(jax => jax.name === 'TeX');
        if (!tex || !startup.output) {
            throw new Error('Scoped MathJax configuration requires a TeX input and an output jax.');
        }
        const options = {...tex.options, ...scope.tex};
        // Administrator package additions/removals have already been resolved by the shared input jax.
        options.packages = [...new Set([...tex.options.packages, ...scope.packages])];
        scope.document = startup.mathjax.document(document, {
            ...mathjax.config.options,
            InputJax: new tex.constructor(options),
            OutputJax: new startup.output.constructor({...startup.output.options}),
        });
    }
    // AJAX can replace children without replacing the registered root.
    const removed = [...scope.document.math].filter(item => !scope.node.contains(item.start.node));
    removed.forEach(item => item.clear());
    scope.document.math.remove(...removed);
    scope.document.options.elements = [scope.node];
    scope.document.reset();
    await scope.document.renderPromise();
};

/**
 * Keep registered scopes out of the shared renderer, even for an ancestor update.
 *
 * MathJax skips data-MJX subtrees regardless of processHtmlClass. This marker is
 * temporary and never grants trust: only the WeakMap can select a scoped renderer.
 *
 * @param {HTMLElement} node Ordinary container
 * @returns {Promise} Rendering completion
 */
export const renderOrdinary = async node => {
    const roots = within(node);
    const previous = roots.map(root => root.getAttribute('data-MJX'));
    roots.forEach(root => root.setAttribute('data-MJX', 'true'));
    try {
        await window.MathJax.typesetPromise([node]);
    } finally {
        roots.forEach((root, index) => {
            if (previous[index] === null) {
                root.removeAttribute('data-MJX');
            } else {
                root.setAttribute('data-MJX', previous[index]);
            }
        });
    }
};

/**
 * Remove a failed registration so it cannot intercept later filter updates.
 *
 * @param {HTMLElement} node Scope root
 */
export const unregister = node => {
    scopes.delete(node);
    node.classList.remove(marker);
};
