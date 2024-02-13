convert -delay 66 -loop 0 *.png -background white -alpha remove animation.gif
convert -delay 66 -loop 0 $(ls *.png | sort -n) -background white -alpha remove animation.gif
