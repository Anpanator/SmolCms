import {assertEqual} from './assert.js';
import {HttpContentType, HttpHeader} from '../../public/js/httpConstants.js';

export function testHttpConstantsAreFrozen() {
    assertEqual(Object.isFrozen(HttpHeader), true);
    assertEqual(Object.isFrozen(HttpContentType), true);
}
