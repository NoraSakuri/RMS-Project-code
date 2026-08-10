'use strict';

const userModal =
    document.getElementById('userModal');

const userForm =
    document.getElementById('userForm');

const searchUser =
    document.getElementById('searchUser');

const roleFilter =
    document.getElementById('roleFilter');

const statusFilter =
    document.getElementById('statusFilter');

const userRows =
    document.querySelectorAll(
        '.users-table tbody tr'
    );

const userModalTitle =
    document.getElementById(
        'userModalTitle'
    );

const editStaffId =
    document.getElementById(
        'editStaffId'
    );

const staffName =
    document.getElementById(
        'staffName'
    );

const username =
    document.getElementById(
        'username'
    );

const password =
    document.getElementById(
        'password'
    );

const passwordLabel =
    document.getElementById(
        'passwordLabel'
    );

const role =
    document.getElementById(
        'role'
    );

const status =
    document.getElementById(
        'status'
    );

const saveUserButton =
    document.getElementById(
        'saveUserButton'
    );

let formMode = 'create';

/*
|--------------------------------------------------------------------------
| Open Add Staff Modal
|--------------------------------------------------------------------------
*/

function openUserModal() {
    formMode = 'create';

    userForm.reset();

    editStaffId.value = '';

    userModalTitle.textContent =
        'Add Staff';

    passwordLabel.textContent =
        'Password';

    password.placeholder =
        'Enter password';

    saveUserButton.textContent =
        'Save Staff';

    userModal.classList.add('show');

    setTimeout(() => {
        staffName.focus();
    }, 100);
}

/*
|--------------------------------------------------------------------------
| Open Edit Staff Modal
|--------------------------------------------------------------------------
*/

function openEditUserModal(button) {
    formMode = 'edit';

    editStaffId.value =
        button.dataset.id || '';

    staffName.value =
        button.dataset.name || '';

    username.value =
        button.dataset.username || '';

    role.value =
        button.dataset.role || '';

    status.value =
        Number(button.dataset.status) === 1
            ? 'Active'
            : 'Inactive';

    password.value = '';

    userModalTitle.textContent =
        'Edit Staff';

    passwordLabel.textContent =
        'New Password';

    password.placeholder =
        'Leave blank to keep current password';

    saveUserButton.textContent =
        'Update Staff';

    userModal.classList.add('show');

    setTimeout(() => {
        staffName.focus();
    }, 100);
}

/*
|--------------------------------------------------------------------------
| Close Modal
|--------------------------------------------------------------------------
*/

function closeUserModal() {
    userModal.classList.remove('show');

    userForm.reset();

    editStaffId.value = '';

    formMode = 'create';
}

userModal.addEventListener(
    'click',
    event => {
        if (event.target === userModal) {
            closeUserModal();
        }
    }
);

document.addEventListener(
    'keydown',
    event => {
        if (
            event.key === 'Escape' &&
            userModal.classList.contains(
                'show'
            )
        ) {
            closeUserModal();
        }
    }
);

/*
|--------------------------------------------------------------------------
| Filter Users
|--------------------------------------------------------------------------
*/

function filterUsers() {
    const searchValue =
        searchUser.value
            .trim()
            .toLowerCase();

    const roleValue =
        roleFilter.value;

    const statusValue =
        statusFilter.value;

    userRows.forEach(row => {
        const name =
            row.children[0]
                .innerText
                .toLowerCase();

        const usernameValue =
            row.children[1]
                .innerText
                .toLowerCase();

        const roleValueInRow =
            row.children[2]
                .innerText
                .trim();

        const statusValueInRow =
            row.children[3]
                .innerText
                .trim();

        const matchesSearch =
            name.includes(searchValue) ||
            usernameValue.includes(
                searchValue
            );

        const matchesRole =
            roleValue === 'All Roles' ||
            roleValueInRow === roleValue;

        const matchesStatus =
            statusValue === 'All Status' ||
            statusValueInRow ===
                statusValue;

        row.style.display =
            matchesSearch &&
            matchesRole &&
            matchesStatus
                ? ''
                : 'none';
    });
}

searchUser.addEventListener(
    'input',
    filterUsers
);

roleFilter.addEventListener(
    'change',
    filterUsers
);

statusFilter.addEventListener(
    'change',
    filterUsers
);

/*
|--------------------------------------------------------------------------
| Create or Update Staff
|--------------------------------------------------------------------------
*/

userForm.addEventListener(
    'submit',
    async event => {
        event.preventDefault();

        const nameValue =
            staffName.value.trim();

        const usernameValue =
            username.value.trim();

        const passwordValue =
            password.value;

        const roleValue =
            role.value;

        const statusValue =
            status.value;

        if (
            nameValue === '' ||
            usernameValue === '' ||
            roleValue === ''
        ) {
            alert(
                'Please complete all required fields.'
            );

            return;
        }

        if (
            formMode === 'create' &&
            passwordValue.length < 6
        ) {
            alert(
                'Password must be at least 6 characters.'
            );

            return;
        }

        if (
            formMode === 'edit' &&
            passwordValue !== '' &&
            passwordValue.length < 6
        ) {
            alert(
                'New password must be at least 6 characters.'
            );

            return;
        }

        const formData =
            new FormData();

        formData.append(
            'name',
            nameValue
        );

        formData.append(
            'username',
            usernameValue
        );

        formData.append(
            'password',
            passwordValue
        );

        formData.append(
            'role',
            roleValue
        );

        formData.append(
            'status',
            statusValue
        );

        let apiUrl =
            '../../back-end/api/users/create.php';

        if (formMode === 'edit') {
            const staffId =
                Number(editStaffId.value);

            if (!staffId) {
                alert(
                    'Invalid staff ID.'
                );

                return;
            }

            formData.append(
                'staff_id',
                staffId
            );

            apiUrl =
                '../../back-end/api/users/update.php';
        }

        const originalButtonText =
            saveUserButton.textContent;

        saveUserButton.disabled = true;

        saveUserButton.textContent =
            formMode === 'edit'
                ? 'Updating...'
                : 'Saving...';

        try {
            const response = await fetch(
                apiUrl,
                {
                    method: 'POST',

                    credentials:
                        'same-origin',

                    body: formData
                }
            );

            const responseText =
                await response.text();

            let result;

            try {
                result = JSON.parse(
                    responseText
                );
            } catch {
                console.error(
                    'Raw staff response:',
                    responseText
                );

                throw new Error(
                    'Server returned an invalid response.'
                );
            }

            if (
                !response.ok ||
                !result.success
            ) {
                throw new Error(
                    result.message ||
                    'Unable to save staff account.'
                );
            }

            alert(result.message);

            closeUserModal();

            window.location.reload();

        } catch (error) {
            console.error(
                'Save staff error:',
                error
            );

            alert(
                error.message ||
                'Unable to save staff account.'
            );

            saveUserButton.disabled =
                false;

            saveUserButton.textContent =
                originalButtonText;
        }
    }
);

/*
|--------------------------------------------------------------------------
| Edit Buttons
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.edit-btn')
    .forEach(button => {
        button.addEventListener(
            'click',
            function () {
                openEditUserModal(
                    this
                );
            }
        );
    });

/*
|--------------------------------------------------------------------------
| Activate / Disable Staff
|--------------------------------------------------------------------------
|
| This section assumes status.php has already been created.
|--------------------------------------------------------------------------
*/

async function updateStaffStatus(
    button,
    isActive
) {
    const row =
        button.closest('tr');

    const staffId =
        Number(row?.dataset.id);

    if (!staffId) {
        alert(
            'Invalid staff ID.'
        );

        return;
    }

    const actionText =
        isActive === 1
            ? 'approve and activate'
            : 'disable';

    const confirmed = confirm(
        `Are you sure you want to ${actionText} this staff account?`
    );

    if (!confirmed) {
        return;
    }

    const originalText =
        button.textContent;

    button.disabled = true;

    button.textContent =
        'Updating...';

    try {
        const response = await fetch(
            '../../back-end/api/users/status.php',
            {
                method: 'POST',

                credentials:
                    'same-origin',

                headers: {
                    'Content-Type':
                        'application/json',

                    Accept:
                        'application/json'
                },

                body: JSON.stringify({
                    staff_id: staffId,
                    is_active: isActive
                })
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(
                responseText
            );
        } catch {
            console.error(
                'Raw status response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (
            !response.ok ||
            !result.success
        ) {
            throw new Error(
                result.message ||
                'Unable to update account status.'
            );
        }

        alert(result.message);

        window.location.reload();

    } catch (error) {
        console.error(
            'Update status error:',
            error
        );

        alert(
            error.message ||
            'Unable to update account status.'
        );

        button.disabled = false;

        button.textContent =
            originalText;
    }
}

document
    .querySelectorAll('.disable-btn')
    .forEach(button => {
        button.addEventListener(
            'click',
            function () {
                updateStaffStatus(
                    this,
                    0
                );
            }
        );
    });

document
    .querySelectorAll('.enable-btn')
    .forEach(button => {
        button.addEventListener(
            'click',
            function () {
                updateStaffStatus(
                    this,
                    1
                );
            }
        );
    });