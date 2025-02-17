# Alt-Text Bulk Manager für Divi-Theme
Tags: divi alt text, divi alt text manager, divi alt text bulk manager, divi alt text bulk, divi alt text bulk edit, divi alt text bulk edit manager, divi alt text bulk edit plugin, divi alt text bulk edit tool  
Tested up to: 6.7.2  
@author: Jens Fuchs und ChatGPT  
@date: 2025-02-14

Problem: Divi ignoriert am Bild im Medienpool hinterlegte Alt-Texte. Der Alt-Text wird nur im Frontend ausgegeben, wenn er direkt im Modul hinterlegt wird. Das ist sehr aufwändig und redundant. 

Dieses Plugin sollte *alle* Bilder, die mit dem Divi-Modul `[et_pb_image]` im Inhaltsbereich der Website in einer langen Liste ausgeben, die KEIN Alt-Text in diesem Modul haben. Hinten lassen sich in jeder Zeile Alt-Texte ergänzen und gesammelt speichern.

## Installation

Einfach aktivieren. Es müssen NICHT alle Alt-Text eingegeben werden, es werden nur die Alt-Texte neu geschrieben, die auch ausgefüllt sind. Insofern kann man das auch inkrementell machen. Das Limit liegt bei 5 Artikeln. Initial werden somit nicht alle Bilder ohne Alt-Tags angezeigt, sondern die von 5 Artikeln. 

## Backup vorher machen

keine Garantie, dass das Plugin fehlerfrei funktioniert. Unbedingt ein Backup der Tabelle wp_posts anfertgien.