import { assertEqual, assertTrue } from './assert.js';
import { el } from '../../public/js/dom.js';

export function testElBuildsElementWithClassAndText() {
    const node = el('p', { className: 'greeting', children: 'Hello' });
    assertTrue(node instanceof HTMLParagraphElement);
    assertEqual(node.className, 'greeting');
    assertEqual(node.textContent, 'Hello');
}

export function testElSetsAttributes() {
    const node = el('input', { attributes: { type: 'password', name: 'password' } });
    assertEqual(node.getAttribute('type'), 'password');
    assertEqual(node.getAttribute('name'), 'password');
}

export function testElAppendsNodesAndStringsAndSkipsEmpty() {
    const child = el('span', { children: 'child' });
    const node = el('div', { children: [child, 'text', null, false, undefined] });
    assertEqual(node.children.length, 1);
    assertEqual(node.childNodes.length, 2, 'element + text node only');
    assertEqual(node.childNodes[1].textContent, 'text');
}

export function testElFlattensNestedChildren() {
    const node = el('ul', { children: [el('li', { children: ['a', ['b']] })] });
    assertEqual(node.querySelector('li')?.textContent, 'ab');
}

export function testElWithoutOptionsYieldsEmptyElement() {
    const node = el('div');
    assertEqual(node.tagName, 'DIV');
    assertEqual(node.className, '');
    assertEqual(node.childElementCount, 0);
}
