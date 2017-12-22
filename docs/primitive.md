# Primitive

The idea behind this is

* Original in GO: [primitive.lol](https://github.com/fogleman/primitive/)
* Javascript Port: [primitive.js](https://github.com/ondras/primitive.js)

This implementation is based on the Javascript port, originally.

## How it works

It tries to find a shape that increases the similarity of the target to the original
image one shape at the time. The result is a reproduction of the original image using
basic shapes. 

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

The SVG output is optimized for size, but it depends directly on the shape count. 10 shapes
result in less then 1kb. With an blur the result gives a good impression of the actual image. 

## Shape Examples

![Triangle](images/primitive-triangle.png?raw=true)

![Rectangle](images/primitive.png?raw=true)

![Rotated Rectangle](images/primitive-rotated-rect.png?raw=true)

![Ellipse](images/primitive-ellipse.png?raw=true)

   
  
          

