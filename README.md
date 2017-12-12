Implementing the JS Canvas API in PHP (around GD). Not fully featured but in a way that allows to 
port code from JS to PHP.

Here a really interesting blog post: https://jmperezperez.com/svg-placeholders/

In this repository I try to experiment with the different ideas from the article.
I try to find out if it is possible to use them in a PHP application.

## Experiments

### Colors

Extracts some colors from an image. The implementation is forked from 
[ColorThiefPHP](https://github.com/ksubileau/color-thief-php). Unlike the original Black and White are
allowed and the handling of the alpha transparency is different.

I treat alpha transparency as it would be with a white background.

![Colors](docs/images/colors.png?raw=true)

### Gradients

A loose adoption of [Gradify](https://github.com/fraser-hemp/gradify). It extracts the
four colors using the palette from the Colors experiment. 

![Gradients](docs/images/gradients.png?raw=true)

### Paths

Traces an bitmap and creates SVG paths. Based on [ImageTracerJS](https://github.com/jankovicsandras/imagetracerjs).
  
![ImageTracer](docs/images/trace-paths.png?raw=true)

### Primitive 

Use [primitive](http://primitive.lol/) shapes (triangle, rect) to create an approximate version of the image. Even
with only a very fe shapes and a strong blur the result is good impression of the image. 
But it is really expensive to create. My implementation is based on 
[primitive.js](https://github.com/ondras/primitive.js).

![Primitive](docs/images/primitive.png?raw=true)
