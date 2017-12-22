# Primitive

* Original in GO: [primitive.lol](https://github.com/fogleman/primitive/)
* Javascript Port: [primitive.js](https://github.com/ondras/primitive.js)

This implementation is based on the Javascript port, originally.

## How it works

* Load the original image
* Create a target image and fill it with the background color
* For n shapes
  * Create m random shapes
    * Find the average color of the shape pixels in the original image
    * Draw the shape on a copy of the target image
    * Compare the result with the original image and compute a score
  * Keep the shape with the highest score
  * Until k worse shapes 
    * Mutate the shape
    * Draw the shape on a copy of the target image
    * Compare the result with the original image and compute a score
    * Keep if better and reset worse counter, otherwise increment worse counter
  * Add shape to SVG
  * Replace target to include latest shape
* Return SVG

## Shape Examples

![Triangle](images/primitive-triangle.png?raw=true)

![Rectangle](images/primitive.png?raw=true)

![Rotated Rectangle](images/primitive-rotated-rect.png?raw=true)

   
  
          

