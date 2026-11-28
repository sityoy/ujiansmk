import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeStudentSelection } from '../../resources/js/student-selection.js';

function element(extra = {}) {
    return {checked:false,disabled:false,hidden:false,listeners:{},children:[],textContent:'',
        addEventListener(event, listener){this.listeners[event]=listener;},
        replaceChildren(){this.children=[];},appendChild(child){this.children.push(child);}, ...extra};
}
function setup({rooms=false,count=3,confirm=true}={}) {
    const students=Array.from({length:count},(_,i)=>element({value:String(i+1)}));
    const all=element(),counts=[element(),element()],ids=element(),warning=element(),button=element(),roomButton=element();
    const deletion=element({querySelector:selector=>selector==='[data-selected-students]'?ids:button});
    const room=rooms?element({querySelector:()=>roomButton}):null;
    const statusIds=element(), statusButton=element(), select=element({value:''}), field=element();
    const reason=element({querySelector:()=>field});
    const status=element({querySelector:selector=>({
        '[data-selected-students]':statusIds, 'button[type="submit"]':statusButton,
        '[data-student-status]':select, '[data-student-reason]':reason,
    }[selector])});
    const browser=element(),questions=[];
    const root={getElementById:id=>({'student-bulk-delete':deletion, 'student-room-bulk':room, 'student-bulk-status':status}[id]),
        querySelector:selector=>selector==='[data-room-select-all]'?all:warning,
        querySelectorAll:selector=>selector==='[data-student-status-form]'?[status]:selector==='[data-room-student]'?students:counts,
        createElement:()=>element(),defaultView:browser};
    initializeStudentSelection(root,message=>{questions.push(message);return confirm;});
    const submit=()=>{const event={prevented:false,preventDefault(){this.prevented=true;}};deletion.listeners.submit(event);return event;};
    return {students,all,counts,ids,warning,button,roomButton,browser,questions,submit,statusIds,statusButton,select,reason,field};
}

test('students can be selected and deleted without choosing a room period',()=>{
    const p=setup();assert.equal(p.button.disabled,true);
    p.students[1].checked=true;p.students[1].listeners.change();
    assert.equal(p.button.disabled,false);assert.equal(p.all.indeterminate,true);
    assert.deepEqual(p.ids.children.map(input=>input.value),['2']);
    assert.equal(p.ids.children[0].name,'student_ids[]');
    assert.equal(p.counts[0].textContent,'1 siswa terpilih.');
});

test('select all and individual changes keep the submitted IDs and both counters in sync',()=>{
    const p=setup({rooms:true});p.all.checked=true;p.all.listeners.change();
    assert.deepEqual(p.ids.children.map(input=>input.value),['1','2','3']);
    assert.equal(p.roomButton.disabled,false);assert.equal(p.all.indeterminate,false);
    p.students[0].checked=false;p.students[0].listeners.change();
    assert.deepEqual(p.ids.children.map(input=>input.value),['2','3']);
    assert.equal(p.all.checked,false);assert.equal(p.all.indeterminate,true);
    assert.ok(p.counts.every(count=>count.textContent==='2 siswa terpilih.'));
});

test('cancelling the confirmation preserves selection and prevents deletion',()=>{
    const p=setup({confirm:false});p.students[0].checked=true;p.students[0].listeners.change();
    assert.equal(p.submit().prevented,true);assert.equal(p.button.disabled,false);
    assert.equal(p.questions.length,1);assert.match(p.questions[0],/1 siswa/);
    assert.equal(p.students[0].checked,true);assert.equal(p.ids.children.length,1);
});

test('confirmation disables repeated submission and pageshow restores the correct state',()=>{
    const p=setup();p.students[0].checked=true;p.students[0].listeners.change();
    assert.equal(p.submit().prevented,false);assert.equal(p.button.disabled,true);
    assert.equal(p.submit().prevented,true);assert.equal(p.questions.length,1);
    p.browser.listeners.pageshow();assert.equal(p.button.disabled,false);
});

test('empty or oversized selections are blocked with no partial hidden selection',()=>{
    const empty=setup({count:0});assert.equal(empty.all.disabled,true);assert.equal(empty.submit().prevented,true);assert.equal(empty.questions.length,0);
    const p=setup({rooms:true,count:501});p.all.checked=true;p.all.listeners.change();
    assert.equal(p.button.disabled,true);assert.equal(p.roomButton.disabled,true);assert.equal(p.warning.hidden,false);assert.equal(p.ids.children.length,0);
    assert.equal(p.submit().prevented,true);assert.equal(p.questions.length,0);
    p.students[0].checked=false;p.students[0].listeners.change();
    assert.equal(p.button.disabled,false);assert.equal(p.warning.hidden,true);assert.equal(p.ids.children.length,500);
});

test('status actions reuse the selected rows and show the reason only for nonactive status',()=>{
    const p=setup();assert.equal(p.statusButton.disabled,true);
    p.students[1].checked=true;p.students[1].listeners.change();
    assert.equal(p.statusButton.disabled,false);
    assert.deepEqual(p.statusIds.children.map(input=>input.value),['2']);
    assert.equal(p.reason.hidden,true);
    p.select.value='0';p.select.listeners.change();
    assert.equal(p.reason.hidden,false);
    assert.equal(p.field.required,true);
    p.select.value='1';p.select.listeners.change();
    assert.equal(p.reason.hidden,true);
    assert.equal(p.field.disabled,true);
});
