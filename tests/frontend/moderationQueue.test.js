import {assertEqual, assertTrue, settle} from './assert.js';
import {ApiError} from '../../public/js/api.js';
import {createModerationQueue} from '../../public/js/moderationQueue.js';

function createQueue({fetchQueue, fetchDetails, submitAction, callbacks = {}, options = {}}) {
    const selected = [];
    const rendered = [];
    const details = [];
    const actions = [];
    const queue = createModerationQueue({
        fetchQueue,
        fetchDetails,
        submitAction: async (action, item) => {
            actions.push([action, item.id]);
            return submitAction?.(action, item);
        },
        renderQueue: (state) => rendered.push(state.items.map((item) => item.id)),
        onSelect: (item) => selected.push(item.id),
        onDetailsLoaded: (item, detail) => details.push([item.id, detail]),
        onDetailsLoading: (item) => callbacks.onDetailsLoading?.(item),
        onDetailsUnavailable: callbacks.onDetailsUnavailable,
        onDetailsError: callbacks.onDetailsError,
        onActionSuccess: callbacks.onActionSuccess,
        onActionError: callbacks.onActionError,
        onUnauthorized: callbacks.onUnauthorized,
        onEmpty: callbacks.onEmpty,
        setActionsDisabled: callbacks.setActionsDisabled,
        shouldEvictDetails: options.shouldEvictDetails,
        ...options,
    });
    return {queue, selected, rendered, details, actions};
}

export async function testModerationQueueIgnoresStaleDetailsAndCachesCurrentDetails() {
    const requests = [];
    const {queue, details} = createQueue({
        fetchQueue: async () => [{id: 7}, {id: 3}],
        fetchDetails: (item) => new Promise((resolve) => requests.push({id: item.id, resolve})),
        submitAction: async () => {},
    });
    await queue.load();
    queue.select(1);
    requests[1].resolve({downloadSrc: '/3'});
    await settle();
    requests[0].resolve({downloadSrc: '/7'});
    await settle();

    assertEqual(details.length, 1);
    assertEqual(details[0][0], 3);
    assertEqual(queue.getCachedDetails(3)?.downloadSrc, '/3');
    assertEqual(queue.getCachedDetails(7), undefined);
}

export async function testModerationQueueKeyboardSelectionAndActionsShareState() {
    const disabledStates = [];
    const {queue, selected, rendered, actions} = createQueue({
        fetchQueue: async () => [{id: 7}, {id: 3}],
        fetchDetails: async (item) => ({downloadSrc: `/${item.id}`}),
        submitAction: async () => {},
        callbacks: {setActionsDisabled: (disabled) => disabledStates.push(disabled)},
    });
    await queue.load();
    const target = document.createElement('section');
    target.addEventListener('keydown', queue.handleKeyboard);
    const next = new KeyboardEvent('keydown', {key: 'ArrowDown', bubbles: true, cancelable: true});
    target.dispatchEvent(next);
    await settle();
    assertTrue(next.defaultPrevented);
    assertEqual(selected.join('|'), '7|3');
    assertEqual(queue.getActiveIndex(), 1);

    const approve = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
    target.dispatchEvent(approve);
    await settle();
    assertTrue(approve.defaultPrevented);
    assertEqual(actions.at(-1).join('|'), 'approve|3');
    assertEqual(queue.getItems().map((item) => item.id).join('|'), '7');
    assertEqual(rendered.at(-1).join('|'), '7');
    assertTrue(disabledStates.includes(true));
}

export async function testModerationQueueActionErrorsRefreshUnlessForbidden() {
    let queueLoads = 0;
    const errorMessages = [];
    const {queue} = createQueue({
        fetchQueue: async () => {
            queueLoads++;
            return [{id: 7}];
        },
        fetchDetails: async () => null,
        submitAction: async () => {
            throw new ApiError(403, null);
        },
        callbacks: {onActionError: (error) => errorMessages.push(error.status)},
    });
    await queue.load();
    await queue.act('approve');
    assertEqual(queueLoads, 1, '403 should not reload the queue');
    assertEqual(errorMessages[0], 403);
}
