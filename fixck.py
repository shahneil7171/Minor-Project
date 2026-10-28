p = r'c:/Users/rcvry/OneDrive/Desktop/Minor Project/OpensourceE-commercewebsite/app/Http/Controllers/CheckoutController.php'
s = open(p).read()
old =  razorpay => Online Payment (UPI / Card / Netbanking),\n cashfree => Online Payment (UPI / Card / Netbanking),\n
new =  simulated => Simulated Online Payment (Demo),\n
s = s.replace(old, new)
open(p, 'w').write(s)
print('done')
