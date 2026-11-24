simport javax.swing.;
import java.awt.;
class Student extends JFrame{
JTextField Stud=new JTextField();
JTextField dept=new JTextField();
JTextField col=new JTextField();
JButton send=new JButton("send");
JButton clear=new JButton("clear");
Student()
{
JPanel panel=new JPanel();
panel.setLayout(new GridLayout(4,4));
panel.add(new JLabel("Student name:"));
panel.add(Stud);
panel.add(new JLabel("department:"));
panel.add(dept);
panel.add(new JLabel("college:"));
panel.add(col);
panel.add(send);
panel.add(clear);
add(panel);
}
public static void main(String args[])
{
Student ss=new Student();
ss.setSize(100,200);
ss.setLocationRelativeTo(null);
ss.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
ss.setVisible(true);
}
}