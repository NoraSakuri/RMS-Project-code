let dashboardData = null;
'use strict';


async function loadDashboard(){

    try{

        const response = await fetch(
            "../../back-end/api/dashboard/get_dashboard.php"
        );


        const result = await response.json();


        if(!result.success){
            throw new Error(result.message);
        }


        dashboardData = result.data;

        document.getElementById("totalStaff").textContent =
        dashboardData.summary.total_staff;

        createCharts();

        document.getElementById("totalOrders").textContent =
        dashboardData.summary.today_orders;


        document.getElementById("totalRevenue").textContent =
        Number(dashboardData.summary.today_revenue)
        .toLocaleString()
        + " MMK";


        document.getElementById("totalTables").textContent =
        dashboardData.summary.available_tables;



        // ==========================
        // Recent Orders
        // ==========================

        const orderTable =
        document.getElementById("recentOrders");


        orderTable.innerHTML="";


        dashboardData.recent_orders.forEach(order=>{


            let statusClass="";


            if(order.status==="COMPLETED"){
                statusClass="success";
            }
            else if(order.status==="PREPARING"){
                statusClass="warning";
            }


            orderTable.innerHTML += `

            <tr>

            <td>
            #${order.order_id}
            </td>


            <td>
            Table ${order.table_number}
            </td>


            <td>
            <span class="badge ${statusClass}">
            ${order.status}
            </span>
            </td>


            <td>
            ${Number(order.total_amount)
            .toLocaleString()} MMK
            </td>


            </tr>

            `;


        });



    }
    catch(error){

        console.error(
            "Dashboard Error:",
            error
        );

    }

}

function createCharts(){

    new Chart(
        document.getElementById("revenueChart"),
        {
            type:"line",

            data:{
                labels: dashboardData.revenue_trend.map(
                    item=>item.label
                ),

                datasets:[
                    {
                        label:"Revenue",

                        data:dashboardData.revenue_trend.map(
                            item=>item.revenue
                        )
                    }
                ]
            }
        }
    );


    new Chart(
        document.getElementById("paymentChart"),
        {
            type:"pie",

            data:{
                labels:dashboardData.payment_methods.map(
                    item=>item.payment_method
                ),

                datasets:[
                    {
                        data:dashboardData.payment_methods.map(
                            item=>item.total_amount
                        )
                    }
                ]
            }
        }
    );


    new Chart(
        document.getElementById("sellingChart"),
        {
            type:"bar",

            data:{
                labels:dashboardData.top_selling_items.map(
                    item=>item.name
                ),

                datasets:[
                    {
                        label:"Quantity Sold",

                        data:dashboardData.top_selling_items.map(
                            item=>item.quantity_sold
                        )
                    }
                ]
            }
        }
    );

}

loadDashboard();

