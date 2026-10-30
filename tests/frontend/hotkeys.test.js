import {assertEqual, assertFalse, assertTrue} from './assert.js';
import {createHotkeyHandler, HOTKEY_KEYS, registerViewHotkeys} from '../../public/js/hotkeys.js';

function pressKey(target, type, key, options = {}) {
    const event = new KeyboardEvent(type, {
        key,
        bubbles: true,
        cancelable: true,
        ...options,
    });
    target.dispatchEvent(event);
    return event;
}

export function testHotkeyHandlerDispatchesFromOutsideTheActiveView() {
    const appRoot = document.createElement('div');
    const header = document.createElement('header');
    const outsideControl = document.createElement('button');
    const main = document.createElement('main');
    const activeView = document.createElement('section');
    const otherView = document.createElement('section');
    header.append(outsideControl);
    main.append(activeView);
    appRoot.append(header, main);

    const dispatched = [];
    const unregisterActive = registerViewHotkeys(activeView, {
        ArrowLeft(event) {
            dispatched.push(`active:${event.key}`);
            event.preventDefault();
        },
    });
    const unregisterOther = registerViewHotkeys(otherView, {
        ArrowRight(event) {
            dispatched.push(`other:${event.key}`);
            event.preventDefault();
        },
    });
    let currentView = activeView;
    const hotkeyHandler = createHotkeyHandler(appRoot, () => currentView);

    try {
        const activeEvent = pressKey(outsideControl, 'keydown', 'ArrowLeft');
        assertTrue(activeEvent.defaultPrevented, 'a shortcut from the header should reach the active view');
        assertEqual(dispatched.join('|'), 'active:ArrowLeft');

        currentView = otherView;
        const unsupportedEvent = pressKey(outsideControl, 'keydown', 'ArrowLeft');
        assertFalse(unsupportedEvent.defaultPrevented, 'the inactive view must not receive shortcuts');
        const otherEvent = pressKey(outsideControl, 'keydown', 'ArrowRight');
        assertTrue(otherEvent.defaultPrevented, 'the new active view should receive its own registered shortcut');
        assertEqual(dispatched.join('|'), 'active:ArrowLeft|other:ArrowRight');
    } finally {
        hotkeyHandler.destroy();
        unregisterActive();
        unregisterOther();
    }
}

export function testHotkeyHandlerWorksWhenAppContainerIsActive(fixture) {
    const appRoot = document.createElement('div');
    appRoot.tabIndex = -1;
    const activeView = document.createElement('section');
    appRoot.append(activeView);
    fixture.append(appRoot);

    let handled = 0;
    const unregister = registerViewHotkeys(activeView, {
        ArrowRight(event) {
            handled++;
            event.preventDefault();
        },
    });
    const hotkeyHandler = createHotkeyHandler(appRoot, () => activeView);

    try {
        appRoot.focus();
        assertEqual(document.activeElement, appRoot, 'the app container should be focusable');
        const event = pressKey(document.activeElement, 'keydown', 'ArrowRight');
        assertTrue(event.defaultPrevented, 'hotkeys should work with focus on the app container');
        assertEqual(handled, 1);
    } finally {
        hotkeyHandler.destroy();
        unregister();
        appRoot.remove();
    }
}

export function testHotkeyHandlerWorksWhenFocusFallsOutsideTheApp(fixture) {
    const appRoot = document.createElement('div');
    const activeView = document.createElement('section');
    const outsideControl = document.createElement('button');
    appRoot.append(activeView);
    fixture.append(appRoot, outsideControl);

    let handled = 0;
    const unregister = registerViewHotkeys(activeView, {
        ArrowLeft(event) {
            handled++;
            event.preventDefault();
        },
    });
    const hotkeyHandler = createHotkeyHandler(appRoot, () => activeView);

    try {
        const event = pressKey(outsideControl, 'keydown', 'ArrowLeft');
        assertTrue(event.defaultPrevented, 'the active view shortcut should still work outside the app root');
        assertEqual(handled, 1);
    } finally {
        hotkeyHandler.destroy();
        unregister();
        appRoot.remove();
        outsideControl.remove();
    }
}

export function testEditableKeyboardEventsRunLocallyButDoNotBubbleUp() {
    const outer = document.createElement('div');
    const appRoot = document.createElement('div');
    const activeView = document.createElement('section');
    const input = document.createElement('input');
    activeView.append(input);
    appRoot.append(activeView);
    outer.append(appRoot);

    let hotkeyCalls = 0;
    let viewBubbleCalls = 0;
    let outerBubbleCalls = 0;
    let inputCalls = 0;
    registerViewHotkeys(activeView, {
        ArrowLeft() {
            hotkeyCalls++;
        },
    });
    const hotkeyHandler = createHotkeyHandler(appRoot, () => activeView);
    activeView.addEventListener('keydown', () => viewBubbleCalls++);
    for (const type of ['keydown', 'keypress', 'keyup']) {
        input.addEventListener(type, () => inputCalls++);
        outer.addEventListener(type, () => outerBubbleCalls++);
    }

    try {
        for (const type of ['keydown', 'keypress', 'keyup']) {
            const event = pressKey(input, type, 'ArrowLeft');
            assertFalse(event.defaultPrevented, `${type} should preserve normal input behavior`);
        }

        assertEqual(inputCalls, 3, 'input-local listeners should still run');
        assertEqual(hotkeyCalls, 0, 'typing should not invoke page shortcuts');
        assertEqual(viewBubbleCalls, 0, 'input key events should not reach view ancestors');
        assertEqual(outerBubbleCalls, 0, 'input key events should not bubble outside the app');
    } finally {
        hotkeyHandler.destroy();
        outer.remove();
    }
}

export function testCentralHotkeyListIncludesAllPageShortcuts() {
    assertEqual(
        HOTKEY_KEYS.join('|'),
        'ArrowLeft|ArrowRight|ArrowUp|ArrowDown|Enter|Delete|Backspace',
    );
}
