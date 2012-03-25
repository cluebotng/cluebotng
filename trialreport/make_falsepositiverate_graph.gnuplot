set terminal png
set output 'falsepositives.png'

set title 'Vandalism Detection Rate by False Positives'
set xlabel 'False Positive Rate'
set ylabel 'Portion of Vandalism'
set xrange [0.0:0.02]
set grid

plot 'thresholdtable.txt' using 3:2 title 'Vandalism Detection Rate' with lines

