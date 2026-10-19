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
