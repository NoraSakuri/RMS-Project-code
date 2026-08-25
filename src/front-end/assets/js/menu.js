'use strict';

/*
|--------------------------------------------------------------------------
| Main Elements
|--------------------------------------------------------------------------
*/

const menuGrid = document.getElementById('menuGrid');

const searchMenu =
    document.getElementById('searchMenu');

const categoryFilter =
    document.getElementById('categoryFilter');


/*
|--------------------------------------------------------------------------
| Menu Modal
|--------------------------------------------------------------------------
*/

const menuModal =
    document.getElementById('menuModal');

const menuForm =
    document.getElementById('menuForm');


const itemId =
    document.getElementById('itemId');

const itemName =
    document.getElementById('itemName');

const itemCategory =
    document.getElementById('itemCategory');

const itemDescription =
    document.getElementById('itemDescription');

const itemPrice =
    document.getElementById('itemPrice');

const itemAvailability =
    document.getElementById('itemAvailability');


/*
|--------------------------------------------------------------------------
| Recipe Modal
|--------------------------------------------------------------------------
*/

const recipeModal =
    document.getElementById('recipeModal');

const recipeRows =
    document.getElementById('recipeRows');

const recipeItemId =
    document.getElementById('recipeItemId');


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

let menuItems = [];

let inventoryItems = [];

let selectedIngredients = new Set();



/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function escapeHtml(value){

    const div =
        document.createElement('div');

    div.textContent =
        String(value ?? '');

    return div.innerHTML;
}



function getCategoryIcon(categoryName){

    const category =
        String(categoryName)
        .toLowerCase();


    if(category === 'rice')
        return '🍚';

    if(category === 'noodle')
        return '🍜';

    if(category === 'curry')
        return '🍛';

    if(category === 'drink')
        return '🥤';

    if(category === 'dessert')
        return '🍰';


    return '🍽️';
}




/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/


async function initializePage(){

    await loadInventoryItems();

    await loadMenuItems();

}


initializePage();




/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
*/


async function loadInventoryItems(){

    try{


        const response =
            await fetch(
                '../../back-end/api/inventory/read.php'
            );


        const result =
            await response.json();



        if(result.success){

            inventoryItems =
                result.data;

        }


    }catch(error){

        console.error(
            'Inventory error:',
            error
        );

    }

}





/*
|--------------------------------------------------------------------------
| Menu Load
|--------------------------------------------------------------------------
*/


async function loadMenuItems(){

    try{


        const response =
            await fetch(
                '../../back-end/api/menu/read.php'
            );


        const result =
            await response.json();



        if(!response.ok ||
            !result.success){

            throw new Error(
                result.message
            );

        }



        menuItems =
            result.data;


        filterMenu();



    }catch(error){


        console.error(error);


        menuGrid.innerHTML =
        `
        <p>
        Unable to load menu items.
        </p>
        `;

    }

}






/*
|--------------------------------------------------------------------------
| Render Menu
|--------------------------------------------------------------------------
*/


function renderMenu(items){


    menuGrid.innerHTML = '';



    if(items.length === 0){

        menuGrid.innerHTML =
        `
        <p>
        No menu items found.
        </p>
        `;


        return;

    }




    items.forEach(item=>{


        const available =
            Number(item.availability) === 1;



        menuGrid.innerHTML +=
        `

        <div class="menu-card">


            <div class="food-icon">

                ${getCategoryIcon(
                    item.category_name
                )}

            </div>


            <h3>

                ${escapeHtml(
                    item.name
                )}

            </h3>



            <span class="menu-category">

                ${escapeHtml(
                    item.category_name
                )}

            </span>



            <p>

                ${
                escapeHtml(
                    item.description ||
                    'No description.'
                )
                }

            </p>




            <div class="card-bottom">


                <span class="price">

                    ${Number(
                        item.price
                    ).toLocaleString()}
                    MMK

                </span>



                <span class="status 
                ${available ?
                'available':
                'unavailable'}">

                    ${
                    available ?
                    'Available':
                    'Unavailable'
                    }

                </span>


            </div>





            <div class="card-actions">


                <button
                class="recipe-btn"
                onclick="
                openRecipe(
                ${item.item_id}
                )">

                    Recipe

                </button>




                <button
                class="edit-btn"
                onclick="
                editMenu(
                ${item.item_id}
                )">

                    Edit

                </button>



                <button
                class="delete-btn"
                onclick="
                deleteMenu(
                ${item.item_id}
                )">

                    Delete

                </button>



            </div>


        </div>


        `;


    });


}






/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/


function filterMenu(){


    const search =
        searchMenu.value
        .trim()
        .toLowerCase();



    const category =
        categoryFilter.value;



    const result =
        menuItems.filter(item=>{


            const matchName =
                item.name
                .toLowerCase()
                .includes(search);



            const matchCategory =
                category === 'all' ||
                String(item.category_id)
                === String(category);



            return matchName &&
            matchCategory;


        });



    renderMenu(result);

}



searchMenu.addEventListener(
'input',
filterMenu
);


categoryFilter.addEventListener(
'change',
filterMenu
);





/*
|--------------------------------------------------------------------------
| Recipe Modal
|--------------------------------------------------------------------------
*/


async function openRecipe(id){


    recipeItemId.value =
        id;


    recipeRows.innerHTML =
        '';



    selectedIngredients.clear();



    const recipes =
        await loadRecipe(id);




    if(recipes.length){


        recipes.forEach(recipe=>{


            addRecipeRow(
                recipe.inventory_id,
                recipe.required_quantity
            );


        });


    }
    else{


        addRecipeRow();


    }




    recipeModal.classList.add(
        'show'
    );


}





function closeRecipe(){


    recipeModal.classList.remove(
        'show'
    );


}






function addRecipeRow(
    selectedId = '',
    quantity = ''
){


    const row =
    document.createElement(
        'div'
    );



    row.className =
        'recipe-row';




    row.innerHTML =
    `


    <select class="ingredient">


        <option value="">

        Select Ingredient

        </option>



        ${
        inventoryItems.map(item=>`

        <option

        value="${item.inventory_id}"

        data-unit="${item.unit_type}"

        ${
        Number(item.inventory_id)
        === Number(selectedId)
        ?
        'selected'
        :
        ''
        }

        >

        ${item.item_name}
        (${item.unit_type})


        </option>


        `).join('')
        }



    </select>




    <input

    class="quantity"

    type="number"

    step="0.01"

    value="${quantity}"

    placeholder="Quantity"

    >




    <span class="unit">

    -

    </span>




    <button type="button">

    Remove

    </button>


    `;



    const select =
    row.querySelector(
        '.ingredient'
    );


    const unit =
    row.querySelector(
        '.unit'
    );



    select.addEventListener(
    'change',
    ()=>{


        const option =
        select.options[
            select.selectedIndex
        ];



        unit.textContent =
        option.dataset.unit ||
        '-';



    });



    row.querySelector(
        'button'
    )
    .onclick =
    ()=>{

        row.remove();

    };



    recipeRows.appendChild(row);


}




/*
|--------------------------------------------------------------------------
| Load Existing Recipe
|--------------------------------------------------------------------------
*/


async function loadRecipe(itemId){


    try{


        const response =
        await fetch(

        `../../back-end/api/recipe/read.php?item_id=${itemId}`

        );



        const result =
        await response.json();



        if(result.success){

            return result.data;

        }


        return [];



    }catch(error){


        console.error(error);

        return [];


    }


}

/*
|--------------------------------------------------------------------------
| Save Menu Item
|--------------------------------------------------------------------------
*/


menuForm.addEventListener(
'submit',
async function(event){


    event.preventDefault();



    const apiUrl =
        itemId.value

        ?

        '../../back-end/api/menu/update.php'

        :

        '../../back-end/api/menu/create.php';




    const formData =
        new FormData(
            menuForm
        );



    try{


        const response =
        await fetch(
            apiUrl,
            {

                method:'POST',

                body:formData

            }
        );



        const result =
        await response.json();



        alert(
            result.message
        );



        if(result.success){


            closeMenuModal();


            await loadMenuItems();


        }



    }
    catch(error){


        console.error(error);


        alert(
            'Unable to save menu item.'
        );


    }


});






/*
|--------------------------------------------------------------------------
| Menu Modal
|--------------------------------------------------------------------------
*/


function openMenuModal(){


    menuForm.reset();


    itemId.value='';



    document.querySelector(
        '#menuModal .modal-header h2'
    )
    .textContent =
    'Add Menu Item';



    menuModal.classList.add(
        'show'
    );


}





function closeMenuModal(){


    menuModal.classList.remove(
        'show'
    );


}







/*
|--------------------------------------------------------------------------
| Edit Menu
|--------------------------------------------------------------------------
*/


function editMenu(id){


    const item =
    menuItems.find(
        menu =>
        Number(menu.item_id)
        === Number(id)
    );



    if(!item){

        return;

    }



    itemId.value =
    item.item_id;



    itemName.value =
    item.name;



    itemCategory.value =
    item.category_id;



    itemDescription.value =
    item.description || '';



    itemPrice.value =
    item.price;



    itemAvailability.value =
    Number(
        item.availability
    );



    document.querySelector(
        '#menuModal .modal-header h2'
    )
    .textContent =
    'Edit Menu Item';



    menuModal.classList.add(
        'show'
    );


}






/*
|--------------------------------------------------------------------------
| Delete Menu
|--------------------------------------------------------------------------
*/


async function deleteMenu(id){


    const confirmDelete =
    confirm(
    'Are you sure you want to delete this menu item?'
    );



    if(!confirmDelete){

        return;

    }



    const formData =
    new FormData();



    formData.append(
        'item_id',
        id
    );



    try{


        const response =
        await fetch(

        '../../back-end/api/menu/delete.php',

        {

            method:'POST',

            body:formData

        }

        );



        const result =
        await response.json();




        alert(
            result.message
        );



        if(result.success){


            await loadMenuItems();


        }



    }
    catch(error){


        console.error(error);



        alert(
        'Unable to delete menu item.'
        );


    }


}








/*
|--------------------------------------------------------------------------
| Save Recipe
|--------------------------------------------------------------------------
*/


async function saveRecipe(){


    const rows =
    document.querySelectorAll(
        '.recipe-row'
    );



    if(rows.length === 0){


        alert(
        'Please add ingredient.'
        );


        return;


    }




    const usedIngredients =
    new Set();




    try{


        for(
            const row of rows
        ){



            const ingredient =
            row.querySelector(
                '.ingredient'
            );



            const quantity =
            row.querySelector(
                '.quantity'
            );



            if(
                !ingredient.value
                ||
                !quantity.value
            ){


                continue;


            }





            const inventoryId =
            Number(
                ingredient.value
            );



            if(
                usedIngredients.has(
                    inventoryId
                )
            ){


                throw new Error(
                'Duplicate ingredient detected.'
                );


            }



            usedIngredients.add(
                inventoryId
            );




            const option =
            ingredient.options[
                ingredient.selectedIndex
            ];





            const data = {


                item_id:
                Number(
                    recipeItemId.value
                ),



                inventory_id:
                inventoryId,



                required_quantity:
                Number(
                    quantity.value
                )



            };





            const response =
            await fetch(

            '../../back-end/api/recipe/create.php',

            {


                method:'POST',


                headers:{


                    'Content-Type':
                    'application/json'


                },



                body:
                JSON.stringify(data)


            }


            );






            const result =
            await response.json();





            if(!result.success){


                throw new Error(
                    result.message
                );


            }


        }





        alert(
        'Recipe saved successfully.'
        );



        closeRecipe();



    }
    catch(error){


        console.error(error);



        alert(
            error.message
        );


    }



}