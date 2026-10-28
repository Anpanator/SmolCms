/** Shared queue selection, detail-loading, keyboard, and moderation actions. */
import {isAuthError} from './forms.js';

/**
 * @param {{
 *   fetchQueue: () => Promise<Array<*>>,
 *   fetchDetails: (item: *, imageId: *) => Promise<object|null>,
 *   submitAction: (action: string, item: *) => Promise<*>,
 *   primaryAction?: string,
 *   secondaryAction?: string,
 *   getItemId?: (item: *) => *,
 *   getImageId?: (item: *) => *,
 *   renderQueue?: (state: {items: Array<*>, selected: *, selectById: (id: *) => void}) => void,
 *   onQueueError?: (error: *) => void,
 *   onEmpty?: () => void,
 *   onSelect?: (item: *, index: number, actionPending: boolean) => void,
 *   onDetailsLoading?: (item: *) => void,
 *   onDetailsLoaded?: (item: *, details: object) => void,
 *   onDetailsUnavailable?: (item: *) => void,
 *   onDetailsError?: (error: *, item: *) => void,
 *   onUnauthorized?: (error: *) => void,
 *   onClearStatus?: () => void,
 *   onActionSuccess?: (action: string, item: *) => void,
 *   onActionError?: (error: *, action: string, item: *) => void,
 *   setActionsDisabled?: (disabled: boolean) => void,
 *   shouldCacheDetails?: (details: object, item: *) => boolean,
 *   shouldEvictDetails?: (item: *, remainingItems: Array<*>) => boolean,
 * }} options
 */
export function createModerationQueue({
    fetchQueue,
    fetchDetails,
    submitAction,
    primaryAction = 'approve',
    secondaryAction = 'reject',
    getItemId = (item) => item?.id,
    getImageId = (item) => item?.id,
    renderQueue = () => {},
    onQueueError = () => {},
    onEmpty = () => {},
    onSelect = () => {},
    onDetailsLoading = () => {},
    onDetailsLoaded = () => {},
    onDetailsUnavailable = () => {},
    onDetailsError = () => {},
    onUnauthorized = () => {},
    onClearStatus = () => {},
    onActionSuccess = () => {},
    onActionError = () => {},
    setActionsDisabled = () => {},
    shouldCacheDetails = (details) => Boolean(details?.downloadSrc),
    shouldEvictDetails = () => true,
}) {
    let items = [];
    let activeItem = null;
    let activeIndex = -1;
    let detailRequestId = 0;
    let queueRequestId = 0;
    let loadingImageId = null;
    let actionPending = false;
    const imageDetails = new Map();

    return {
        load,
        render,
        select,
        selectById,
        act,
        handleKeyboard,
        getItems: () => [...items],
        getActiveItem: () => activeItem,
        getActiveIndex: () => activeIndex,
        getActionPending: () => actionPending,
        getCachedDetails: (imageId) => imageDetails.get(imageId),
    };

    async function load() {
        const request = ++queueRequestId;
        try {
            const loadedItems = await fetchQueue();
            if (request !== queueRequestId) {
                return;
            }
            items = Array.isArray(loadedItems) ? loadedItems : [];
            render();
        } catch (error) {
            if (request !== queueRequestId) {
                return;
            }
            if (isAuthError(error)) {
                onUnauthorized(error);
                return;
            }
            onQueueError(error);
        }
    }

    function render(preferredIndex = null) {
        const previousItemId = activeItem === null ? null : getItemId(activeItem);
        renderQueue({items: [...items], selected: activeItem, selectById});

        if (items.length === 0) {
            activeItem = null;
            activeIndex = -1;
            detailRequestId++;
            loadingImageId = null;
            onEmpty();
            setActionsDisabled(true);
            return;
        }

        let nextIndex = Number.isInteger(preferredIndex)
            ? preferredIndex
            : items.findIndex((item) => getItemId(item) === previousItemId);
        if (nextIndex < 0 || nextIndex >= items.length) {
            nextIndex = 0;
        }
        select(nextIndex);
    }

    function select(index) {
        const item = items[index];
        if (!item) {
            return;
        }

        const sameItem = activeItem !== null && getItemId(activeItem) === getItemId(item);
        activeItem = item;
        activeIndex = index;
        onSelect(item, index, actionPending);
        setActionsDisabled(actionPending);

        const imageId = getImageId(item);
        const cachedDetails = imageDetails.get(imageId);
        if (cachedDetails) {
            onDetailsLoaded(item, cachedDetails);
            return;
        }
        if (sameItem && loadingImageId === imageId) {
            return;
        }

        onDetailsLoading(item);
        const request = ++detailRequestId;
        loadingImageId = imageId;
        void loadDetails(item, imageId, getItemId(item), request);
    }

    async function loadDetails(item, imageId, itemId, request) {
        try {
            const details = await fetchDetails(item, imageId);
            if (request !== detailRequestId || getItemId(activeItem) !== itemId) {
                return;
            }
            loadingImageId = null;
            if (!details) {
                onDetailsUnavailable(item);
                return;
            }
            if (shouldCacheDetails(details, item)) {
                imageDetails.set(imageId, details);
            }
            onDetailsLoaded(item, details);
        } catch (error) {
            if (request !== detailRequestId || getItemId(activeItem) !== itemId) {
                return;
            }
            loadingImageId = null;
            if (isAuthError(error)) {
                onUnauthorized(error);
                return;
            }
            onDetailsError(error, item);
        }
    }

    function selectById(id) {
        const index = items.findIndex((item) => getItemId(item) === id);
        if (index >= 0) {
            select(index);
        }
    }

    async function act(action) {
        const item = activeItem;
        if (!item || actionPending) {
            return;
        }

        onClearStatus();
        actionPending = true;
        setActionsDisabled(true);
        try {
            await submitAction(action, item);
            const removedIndex = items.findIndex((entry) => getItemId(entry) === getItemId(item));
            if (removedIndex < 0) {
                return;
            }

            const previousActiveId = activeItem === null ? null : getItemId(activeItem);
            items.splice(removedIndex, 1);
            const imageId = getImageId(item);
            if (shouldEvictDetails(item, items)) {
                imageDetails.delete(imageId);
            }
            const nextIndex = previousActiveId === getItemId(item)
                ? Math.min(removedIndex, items.length - 1)
                : items.findIndex((entry) => getItemId(entry) === previousActiveId);
            if (previousActiveId === getItemId(item)) {
                activeItem = null;
            }
            onActionSuccess(action, item);
            render(nextIndex);
        } catch (error) {
            if (isAuthError(error)) {
                onUnauthorized(error);
                return;
            }
            onActionError(error, action, item);
            if ((error?.status ?? 0) !== 403) {
                await load();
            }
        } finally {
            actionPending = false;
            setActionsDisabled(!activeItem);
        }
    }

    function handleKeyboard(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]'))) {
            return;
        }
        if (!activeItem) {
            return;
        }

        switch (event.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                select(Math.max(0, activeIndex - 1));
                break;
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                select(Math.min(items.length - 1, activeIndex + 1));
                break;
            case 'Enter':
                event.preventDefault();
                if (!event.repeat) {
                    act(primaryAction);
                }
                break;
            case 'Delete':
            case 'Backspace':
                event.preventDefault();
                if (!event.repeat) {
                    act(secondaryAction);
                }
                break;
        }
    }
}
