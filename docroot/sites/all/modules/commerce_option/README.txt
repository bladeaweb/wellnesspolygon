Use case
-----------------
See https://www.drupal.org/project/commerce_option

Setup:
---------------
1) Create an option set (Administration -> Store > Products > Option Sets)
2) Add some fields to your option set. These fields will be presented as
    customizable options to the user in the add to cart form.
3) Link this new option set to a commerce product type through an Entity
    Reference field. Do not enable the "function as an attribute field" option.
4) Create a commerce product and select the option set you just created.
5) Add your product to the product display of your choice. Your options should
    now show in the Add to cart form.
6) To get the options to show up in your cart and checkout flow you have to
    adjust all the concerning Views: Add a relationship (look for "Commerce
    option") and add use those to add your data.

Workings
--------------
Commerce options and line items have a one-on-one relationship. This means that
for every new possible option combination chosen by a user, a new line item will
be created (added to cart) for the chosen product.
