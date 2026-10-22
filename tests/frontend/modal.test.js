import {assertFalse, assertEqual, assertTrue} from './assert.js';
import {createConfirmDialog, createModal} from '../../public/js/modal.js';

export function testModalHasDialogSemanticsAndClosesFromBackdrop(fixture) {
    const opener = document.createElement('button');
    fixture.append(opener);
    opener.focus();
    const modal = createModal({
        content: document.createElement('p'),
        labelledBy: 'sample-dialog-title',
    });
    const panel = modal.element.querySelector('.modal-panel');

    assertTrue(document.body.contains(modal.element));
    assertEqual(modal.element.getAttribute('role'), 'dialog');
    assertEqual(modal.element.getAttribute('aria-modal'), 'true');
    assertEqual(modal.element.getAttribute('aria-labelledby'), 'sample-dialog-title');
    assertTrue(panel !== null);
    panel.click();
    assertTrue(document.body.contains(modal.element), 'clicking the panel must not close the modal');

    modal.element.click();
    assertFalse(document.body.contains(modal.element));
    assertEqual(document.activeElement, opener, 'closing should restore focus to the opener');
}

export function testModalClosesOnEscape() {
    const modal = createModal({content: 'Modal content'});
    const event = new KeyboardEvent('keydown', {key: 'Escape', bubbles: true, cancelable: true});
    document.dispatchEvent(event);

    assertTrue(event.defaultPrevented);
    assertFalse(document.body.contains(modal.element));
}

export async function testConfirmDialogResolvesTrueOnConfirm() {
    const dialog = createConfirmDialog({
        message: 'Delete this record?',
        confirmLabel: 'Delete',
        cancelLabel: 'Keep',
    });
    const buttons = dialog.element.querySelectorAll('.modal-actions button');

    assertEqual(dialog.element.getAttribute('aria-labelledby'), dialog.element.querySelector('.modal-title').id);
    assertEqual(dialog.element.querySelector('.modal-message').textContent, 'Delete this record?');
    assertEqual(buttons[0].textContent, 'Keep');
    assertEqual(buttons[1].textContent, 'Delete');
    buttons[1].click();
    assertTrue(await dialog.result);
    assertFalse(document.body.contains(dialog.element));
}

export async function testConfirmDialogConfirmsOnEnter(fixture) {
    const opener = document.createElement('button');
    fixture.append(opener);
    opener.focus();
    const dialog = createConfirmDialog({message: 'Confirm with Enter?', confirmLabel: 'Delete'});
    assertEqual(document.activeElement, dialog.element, 'the dialog should receive focus when opened');

    const event = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
    dialog.element.dispatchEvent(event);

    assertTrue(event.defaultPrevented, 'Enter should be handled by the confirmation dialog');
    assertTrue(await dialog.result, 'Enter should confirm the dialog');
    assertFalse(document.body.contains(dialog.element));
    assertEqual(document.activeElement, opener, 'confirming should restore focus to the opener');
}

export async function testConfirmDialogDoesNotInterceptEnterOnFocusedCancelButton() {
    const dialog = createConfirmDialog({message: 'Keep with Enter?'});
    const cancel = dialog.element.querySelector('.modal-cancel');
    cancel.focus();

    const event = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
    cancel.dispatchEvent(event);

    assertFalse(event.defaultPrevented, 'Enter on Cancel should keep its native button behavior');
    assertTrue(document.body.contains(dialog.element), 'the dialog should stay open');
    cancel.click();
    assertFalse(await dialog.result, 'clicking Cancel should still cancel');
}

export async function testGenericModalDoesNotConfirmOnEnter() {
    const modal = createModal({content: 'Plain modal'});
    const event = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
    document.dispatchEvent(event);

    assertFalse(event.defaultPrevented, 'Enter should not be intercepted outside confirmation dialogs');
    assertTrue(document.body.contains(modal.element));
    modal.close();
}

export async function testConfirmDialogCancelsOnEscape(fixture) {
    const opener = document.createElement('button');
    fixture.append(opener);
    opener.focus();
    const dialog = createConfirmDialog({message: 'Escape?'});

    const event = new KeyboardEvent('keydown', {key: 'Escape', bubbles: true, cancelable: true});
    dialog.element.dispatchEvent(event);

    assertTrue(event.defaultPrevented, 'Escape should be handled by the dialog');
    assertFalse(await dialog.result, 'Escape should cancel the dialog');
    assertFalse(document.body.contains(dialog.element));
    assertEqual(document.activeElement, opener, 'cancelling should restore focus to the opener');
}

export async function testConfirmDialogTreatsCancelEscapeAndBackdropAsCancellation() {
    const canceled = createConfirmDialog({message: 'Cancel?'});
    canceled.element.querySelector('.modal-cancel').click();
    assertFalse(await canceled.result);

    const escaped = createConfirmDialog({message: 'Escape?'});
    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true, cancelable: true}));
    assertFalse(await escaped.result);

    const backdrop = createConfirmDialog({message: 'Backdrop?'});
    backdrop.element.click();
    assertFalse(await backdrop.result);
}
