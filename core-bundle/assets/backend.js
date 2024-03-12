import { Application } from '@hotwired/stimulus'
import { definitionForModuleAndIdentifier, identifierForContextKey } from '@hotwired/stimulus-webpack-helpers'
import * as Turbo from "@hotwired/turbo"

import './scripts/mootao.js'
import './scripts/core.js'
import './scripts/limit-height.js'
import './scripts/modulewizard.js'
import './scripts/sectionwizard.js'
import './scripts/tips.js'

/* Stimulus */
// Start Stimulus application and register all controllers with `contao--` prefix.
const application = Application.start()
application.debug = process.env.NODE_ENV === 'development'

const context = require.context('./controllers', true, /\.js$/)
application.load(context.keys().map((key) => {
    const identifier = identifierForContextKey(key);
    if (identifier) {
        return definitionForModuleAndIdentifier(context(key), `contao--${ identifier }`);
    }
}).filter((value) => value));

/* Turbo support */
// Cancel all prefetch requests that contain a request token
document.documentElement.addEventListener('turbo:before-prefetch', e => {
    if ((new URLSearchParams(e.target.href)).has('rt')) {
        e.preventDefault();
    }
});

// Make mootools scripts reinitialize themselves
document.documentElement.addEventListener('turbo:render', () => {
    window.fireEvent('domready');
});
