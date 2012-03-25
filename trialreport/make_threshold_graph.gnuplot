set terminal png
set output 'thresholds.png'

set title 'Detection Rates By Threshold'
set xlabel 'Score Vandalism Threshold'
set ylabel 'Detection Rate'

plot 'thresholdtable.txt' using 1:2 title 'Correct Positive %' with lines, 'thresholdtable.txt' using 1:3 title 'False Positive %' with lines

