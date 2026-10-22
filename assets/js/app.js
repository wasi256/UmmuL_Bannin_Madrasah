// Modal system - replaces native browser dialogs that can be hidden behind window
const modalCounter = 0;

function showCustomModal(title, message, buttons) {
    modalCounter++;
    const modalId = 'modal-' + modalCounter;
    const modal = document.createElement('div');
    modal.id = modalId;
    modal.styleCssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;
    `;
    modal.innerHTML = `
        <div style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 100%; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; max-height: 90%; overflow-y: auto;">
            <h3 style="margin: 0 0 15px; color: #1b5e20;">${title}</h3>
            <p style="margin: 10px 0; line-height: 1.5;">${message}</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
    `;
    buttons.forEach(btn => {
        modal.innerHTML += `<button style="padding: 8px 15px; border: none; border-radius: 4px; background: ${btn.style || '#1b5e20'}; color: white; cursor: pointer; flex: 1;">${btn.text}</button>`;
    });
    modal.innerHTML += `</div></div>`;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Add click listeners to buttons
    const btns = modal.querySelectorAll('button');
    btns.forEach((btn, i) => {
        btn.onclick = () => {
            modal.remove();
            if (buttons[i].callback) buttons[i].callback();
        };
    });
    
    // Return function to close/remove modal
    return () => { modal.remove(); };
}

function confirmEdit(className) {
    return new Promise(resolve => {
        const close = showCustomModal(
            'Confirm',
            'Edit fee for ' + className + '?',
            [
                { text: 'Yes', callback: () => resolve(true) },
                { text: 'No', style: '#d32f2f', callback: () => resolve(false) }
            ]
        );
    });
}

function promptFee(classObj) {
    return new Promise(resolve => {
        const close = showCustomModal(
            'Enter Fee',
            'Enter new fee amount (UGX):',
            [
                { text: 'OK', style: '#1b5e20', callback: () => {
                    const input = document.querySelector(`#modal-${modalCounter} input`);
                    resolve(input ? Number(input.value) : classObj.term_fee);
                }},
                { text: 'Cancel', style: '#d32f2f', callback: () => resolve(null) }
            ]
        );
        
        setTimeout(() => {
            const existingModal = document.getElementById('modal-' + modalCounter);
            if (existingModal) {
                const input = document.createElement('input');
                input.type = 'number';
                input.value = classObj.term_fee;
                input.style = 'width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;';
                existingModal.querySelector('p').appendChild(input);
            }
        }, 50);
    });
}

async function editFee(className) {
    const confirmed = await confirmEdit(className);
    if (!confirmed) return;
    
    const displayClasses = classes.slice(0, 10);
    const classObj = displayClasses.find(c => c.class_name === className);
    if (!classObj) {
        alert('Class not found in displayed list.');
        return;
    }
    
    const newFee = await promptFee(classObj);
    if (newFee === null || isNaN(newFee) || newFee < 0) {
        alert('Please enter a valid non-negative amount.');
        return;
    }
    
    try {
        const response = await fetch(`/api/classes/${classObj.class_id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ term_fee: Number(newFee) })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not update fee.');
        alert('Fee updated successfully: UGX ' + formatNumber(data.term_fee));
        await loadFeesTable();
    } catch (err) {
        console.error('Edit fee error:', err);
        alert('Error: ' + (err.message || 'Unknown error updating fee'));
    }
}

async function deleteFee(feeKey) {
    if (!confirm('Are you sure you want to delete this fee default?')) return;
    try {
        const response = await fetch(`${API_BASE}fees/${feeKey}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not delete fee.');
        alert('Fee deleted successfully.');
        await loadFeesTable();
    } catch (err) {
        console.error('Delete fee error:', err);
        alert('Error: ' + (err.message || 'Unknown error deleting fee'));
    }
}
